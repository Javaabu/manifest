<?php

namespace Javaabu\Manifest;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Component;
use Illuminate\View\FileViewFinder;
use Javaabu\Manifest\Commands\CheckCommand;
use Javaabu\Manifest\Commands\DiscoverCommand;
use Javaabu\Manifest\Commands\ListCommand;
use Javaabu\Manifest\Commands\OverrideCommand;
use Javaabu\Manifest\Commands\TypesCommand;
use Javaabu\Manifest\Http\Controllers\DistAssetsController;
use Javaabu\Manifest\Http\Middleware\InjectAssets;

class ManifestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/manifest.php', 'manifest');

        $this->app->singleton(Manifest::class);
        $this->app->scoped(AssetRequirements::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'manifest');

        $this->registerComponentHook();
        $this->registerDirectives();
        $this->registerMiddleware();
        $this->registerDistRoute();
        $this->registerInertiaTestingPages();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            CheckCommand::class,
            DiscoverCommand::class,
            ListCommand::class,
            OverrideCommand::class,
            TypesCommand::class,
        ]);

        $this->rediscoverAfterPackageDiscovery();

        $this->app->booted(fn () => $this->registerPackagePublishing());

        $this->publishes([
            __DIR__ . '/../config/manifest.php' => config_path('manifest.php'),
        ], 'manifest-config');

        $this->publishes([
            __DIR__ . '/../lang' => $this->app->langPath('vendor/manifest'),
        ], 'manifest-translations');
    }

    /**
     * Blade only makes a class component through the container when some of
     * its constructor arguments are missing, so the compiled component tag is
     * hooked too: right after the component is made, before it renders.
     */
    protected function registerComponentHook(): void
    {
        $this->app->afterResolving(Component::class, function (Component $component) {
            $this->app->make(Manifest::class)->requireFor($component);
        });

        Blade::precompiler(function (string $value) {
            return str_replace(
                '<?php $component->withAttributes(',
                '<?php app(\\Javaabu\\Manifest\\Manifest::class)->requireFor($component); ?>' . "\n" . '<?php $component->withAttributes(',
                $value,
            );
        });
    }

    /**
     * Laravel apps run package:discover on every composer install, update and
     * dump-autoload. The new packages' providers aren't loaded in that
     * process, so manifest:discover runs in a fresh one.
     */
    protected function rediscoverAfterPackageDiscovery(): void
    {
        Event::listen(CommandFinished::class, function (CommandFinished $event) {
            if ($event->command !== 'package:discover' || $event->exitCode !== 0) {
                return;
            }

            Process::path($this->app->basePath())->run([PHP_BINARY, 'artisan', 'manifest:discover']);
        });
    }

    /**
     * Packages register in boot(), so their publishing is declared once
     * everything has booted.
     */
    protected function registerPackagePublishing(): void
    {
        $manifest = $this->app->make(Manifest::class);

        foreach ($manifest->packages() as $name => $package) {
            $assets = [];

            foreach ($package->assetFolders() as $folder) {
                $assets[$package->path($folder)] = $manifest->overridePath($name, $folder);
            }

            if ($assets) {
                $this->publishes($assets, ['manifest-assets', "manifest-assets-{$name}"]);
            }

            if ($dist = $manifest->distPath($name)) {
                $this->publishes([
                    $dist => public_path($manifest->distUrlPath($name)),
                ], ['manifest-dist', "manifest-dist-{$name}"]);
            }
        }
    }

    /**
     * inertia-laravel checks that a page exists (in assertInertia(), and when
     * rendering with `inertia.pages.ensure_pages_exist`) with a view finder,
     * which understands package::Page as a namespace. v2 binds it as
     * `inertia.testing.view-finder` and v3 as `inertia.view-finder`.
     */
    protected function registerInertiaTestingPages(): void
    {
        foreach (['inertia.view-finder', 'inertia.testing.view-finder'] as $binding) {
            $this->app->afterResolving($binding, function (FileViewFinder $finder) {
                $this->registerPackagePages($finder);
            });
        }
    }

    protected function registerPackagePages(FileViewFinder $finder): void
    {
        $manifest = $this->app->make(Manifest::class);

        foreach ($manifest->packages() as $name => $package) {
            if (! $package->pagesPath()) {
                continue;
            }

            $finder->replaceNamespace($name, [
                $manifest->overridePath($name, $package->pagesPath()),
                $package->path($package->pagesPath()),
            ]);
        }
    }

    protected function registerDistRoute(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $base = trim((string) (config('manifest.dist_url') ?? 'vendor/manifest'), '/');

        Route::get("{$base}/{package}/{path}", DistAssetsController::class)
            ->where('package', '[a-z0-9][a-z0-9-]*')
            ->where('path', '.+')
            ->name('manifest.dist');
    }

    protected function registerDirectives(): void
    {
        Blade::directive('manifestStyles', fn (string $expression) => $this->placeholderDirective('styles', $expression));
        Blade::directive('manifestScripts', fn (string $expression) => $this->placeholderDirective('scripts', $expression));

        Blade::directive('manifestRequire', function (string $expression) {
            return "<?php app(\\Javaabu\\Manifest\\Manifest::class)->require({$expression}); ?>";
        });
    }

    protected function placeholderDirective(string $kind, string $expression): string
    {
        $scope = trim($expression) === '' ? '' : $expression;

        return "<?php echo app(\\Javaabu\\Manifest\\Manifest::class)->placeholder('{$kind}', [{$scope}]); ?>";
    }

    protected function registerMiddleware(): void
    {
        if (! $this->app->bound(HttpKernel::class)) {
            return;
        }

        $kernel = $this->app->make(HttpKernel::class);

        if (! $kernel instanceof Kernel) {
            return;
        }

        if ($kernel->hasMiddleware(InjectAssets::class)) {
            return;
        }

        $kernel->pushMiddleware(InjectAssets::class);
    }
}
