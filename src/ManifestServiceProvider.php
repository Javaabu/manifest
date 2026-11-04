<?php

namespace Javaabu\Manifest;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Component;
use Javaabu\Manifest\Commands\DiscoverCommand;
use Javaabu\Manifest\Commands\ListCommand;
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

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            DiscoverCommand::class,
            ListCommand::class,
        ]);

        $this->rediscoverAfterPackageDiscovery();

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
