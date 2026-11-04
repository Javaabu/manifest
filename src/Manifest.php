<?php

namespace Javaabu\Manifest;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\HtmlString;
use Javaabu\Manifest\Attributes\RequiresAssets;
use Javaabu\Manifest\Contracts\DeclaresAssets;
use Javaabu\Manifest\Exceptions\CircularDependencyException;
use Javaabu\Manifest\Exceptions\UnknownAssetException;
use Javaabu\Manifest\Rendering\Injector;
use ReflectionClass;

class Manifest
{
    /** @var array<string, Package> */
    protected array $packages = [];

    /** @var array<class-string, array<int, string>> */
    protected array $class_requirements = [];

    /** @var array<string, string>|null real path => vendor/{name} */
    protected ?array $vendor_links = null;

    public function __construct(
        protected Application $app,
    ) {}

    /**
     * Register a package's assets, or get the package to add more. The path
     * is the package's resources folder; entries are relative to it.
     */
    public function package(string $name, ?string $path = null): Package
    {
        if (isset($this->packages[$name])) {
            return $this->packages[$name];
        }

        if ($path === null) {
            throw UnknownAssetException::package($name);
        }

        return $this->packages[$name] = new Package($name, $path);
    }

    public function hasPackage(string $name): bool
    {
        return isset($this->packages[$name]);
    }

    /** @return array<string, Package> */
    public function packages(): array
    {
        return $this->packages;
    }

    public function has(string $id): bool
    {
        [$package, $name] = Asset::parseId($id);

        return isset($this->packages[$package])
            && $this->packages[$package]->asset($name) !== null;
    }

    public function find(string $id, ?string $required_by = null): Asset
    {
        [$package, $name] = Asset::parseId($id);

        $asset = isset($this->packages[$package])
            ? $this->packages[$package]->asset($name)
            : null;

        if (! $asset) {
            throw UnknownAssetException::asset($id, $required_by);
        }

        return $asset;
    }

    /**
     * Every asset, in dependency order.
     *
     * @return array<int, Asset>
     */
    public function all(): array
    {
        $ids = [];

        foreach ($this->packages as $package) {
            foreach ($package->assets() as $asset) {
                $ids[] = $asset->id();
            }
        }

        return $this->resolve($ids);
    }

    /**
     * Expands asset ids, or package names for all of a package's assets,
     * into the assets and their dependencies, in dependency order: every
     * asset comes after the assets it depends on, and each appears once.
     *
     * @param  array<int, string>  $ids
     * @return array<int, Asset>
     */
    public function resolve(array $ids): array
    {
        $resolved = [];

        foreach ($ids as $id) {
            foreach ($this->expand($id) as $asset_id) {
                $this->visit($asset_id, $resolved, []);
            }
        }

        return array_values($resolved);
    }

    /**
     * Load assets on the current page: asset ids (package::name), or package
     * names for all of a package's assets. Unknown assets and broken
     * dependencies throw here, while the view is rendering.
     *
     * @param  string|array<int, string>  ...$ids
     */
    public function require(string|array ...$ids): static
    {
        $ids = array_merge(...array_map(fn ($id) => (array) $id, $ids));

        $requirements = $this->requirements();

        foreach ($this->resolve($ids) as $asset) {
            $requirements->add($asset->id());
        }

        return $this;
    }

    /**
     * Require what a component declares with #[RequiresAssets] or
     * DeclaresAssets.
     */
    public function requireFor(object $component): void
    {
        $ids = $this->class_requirements[$component::class] ??= $this->declaredOn($component::class);

        if ($component instanceof DeclaresAssets) {
            $ids = [...$ids, ...$component->requiredAssets()];
        }

        if ($ids) {
            $this->require($ids);
        }
    }

    public function isRequired(string $id): bool
    {
        return $this->requirements()->has($id);
    }

    /**
     * The assets the current request needs, in dependency order.
     *
     * @return array<int, Asset>
     */
    public function required(): array
    {
        return $this->resolve($this->requirements()->ids());
    }

    /**
     * Render the required styles now (for views rendered outside a request,
     * like mail). The directives are better in pages: they also pick up
     * components rendered after them.
     *
     * @param  array<int, string>  $scope  packages or asset ids; all when empty
     */
    public function renderStyles(array $scope = []): HtmlString
    {
        return $this->renderNow('styles', $scope);
    }

    /**
     * Render the required scripts now. See renderStyles().
     *
     * @param  array<int, string>  $scope  packages or asset ids; all when empty
     */
    public function renderScripts(array $scope = []): HtmlString
    {
        return $this->renderNow('scripts', $scope);
    }

    /**
     * What @manifestStyles and @manifestScripts print: a marker filled with
     * tags once the response is ready. Named assets are required.
     *
     * @param  array<int, string>  $scope
     */
    public function placeholder(string $kind, array $scope = []): HtmlString
    {
        $scope = array_values(array_filter($scope));

        foreach ($scope as $item) {
            str_contains($item, '::')
                ? $this->require($item)
                : $this->package($item);
        }

        return new HtmlString(Injector::placeholder($kind, $scope ? implode(',', $scope) : '*'));
    }

    public function requirements(): AssetRequirements
    {
        return $this->app->make(AssetRequirements::class);
    }

    /**
     * The registered package that owns the asset.
     */
    public function packageFor(Asset $asset): Package
    {
        return $this->package($asset->package);
    }

    /**
     * The folder where an app overrides a package's resources, file by file.
     */
    public function overridePath(string $package, ?string $relative = null): string
    {
        $base = rtrim(config('manifest.overrides_path') ?: $this->app->resourcePath('vendor'), '/\\');

        $path = "{$base}/{$package}";

        if (! $relative) {
            return $path;
        }

        return $path . '/' . ltrim($relative, '/');
    }

    /**
     * The file a package's relative path resolves to: the app's override if
     * it has one, otherwise the package's own.
     */
    public function resolvePath(string $package, string $relative): string
    {
        $override = $this->overridePath($package, $relative);

        if (is_file($override)) {
            return $override;
        }

        return $this->package($package)->path($relative);
    }

    /**
     * The absolute path of an asset's entry, with overrides applied.
     */
    public function entryPath(Asset $asset): string
    {
        return $this->resolvePath($asset->package, $asset->entry);
    }

    /**
     * The real path of a package's prebuilt dist folder, if it has one.
     */
    public function distPath(string $package): ?string
    {
        if (! isset($this->packages[$package])) {
            return null;
        }

        $dist = $this->packages[$package]->distPath();

        if (! $dist) {
            return null;
        }

        return realpath($this->packages[$package]->path($dist)) ?: null;
    }

    /**
     * The URL path a package's prebuilt files are served from, and published to
     * under public/.
     */
    public function distUrlPath(string $package): string
    {
        $base = trim((string) (config('manifest.dist_url') ?? 'vendor/manifest'), '/');

        return "{$base}/{$package}";
    }

    public function isOverridden(Asset $asset): bool
    {
        return is_file($this->overridePath($asset->package, $asset->entry));
    }

    /**
     * A path relative to the app's root, as Vite inputs and the Vite
     * manifest expect. Packages installed from a Composer path repository
     * are symlinked into vendor/, so their real paths map back to vendor/.
     */
    public function relativeToBase(string $path): string
    {
        $base = $this->normalizePath($this->app->basePath()) . '/';

        $path = $this->normalizePath($path);

        if (str_starts_with($path, $base)) {
            return substr($path, strlen($base));
        }

        foreach ($this->vendorLinks() as $real => $link) {
            if ($path === $real || str_starts_with($path, "{$real}/")) {
                return $link . substr($path, strlen($real));
            }
        }

        return $path;
    }

    /**
     * What the Vite plugin reads (php artisan manifest:discover).
     *
     * @return array<string, mixed>
     */
    public function toDiscoveryArray(): array
    {
        $packages = [];

        foreach ($this->packages as $name => $package) {
            $packages[$name] = [
                'path' => $this->relativeToBase($package->path()),
                'alias' => $package->importAlias(),
                'js' => $package->jsPath(),
                'pages' => $package->pagesPath(),
                'dist' => $package->distPath(),
                'overrides' => $this->relativeToBase($this->overridePath($name)),
                'dependencies' => (object) $package->npmDependencies(),
                'peerDependencies' => (object) $package->npmPeerDependencies(),
            ];
        }

        $assets = array_map(fn (Asset $asset) => [
            'id' => $asset->id(),
            'package' => $asset->package,
            'name' => $asset->name,
            'type' => $asset->type->value,
            'entry' => $asset->entry,
            'input' => $this->relativeToBase($this->entryPath($asset)),
            'dependencies' => $asset->dependencies,
        ], $this->all());

        return [
            'version' => 1,
            'packages' => (object) $packages,
            'assets' => $assets,
        ];
    }

    protected function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * @return array<string, string>
     */
    protected function vendorLinks(): array
    {
        if ($this->vendor_links !== null) {
            return $this->vendor_links;
        }

        $this->vendor_links = [];

        $installed = $this->app->basePath('vendor/composer/installed.json');

        if (! is_file($installed)) {
            return $this->vendor_links;
        }

        $data = json_decode((string) file_get_contents($installed), true);

        foreach ($data['packages'] ?? $data ?? [] as $package) {
            if (($package['dist']['type'] ?? null) !== 'path' || empty($package['name'])) {
                continue;
            }

            $link = "vendor/{$package['name']}";
            $real = realpath($this->app->basePath($link));

            if ($real) {
                $this->vendor_links[$this->normalizePath($real)] = $link;
            }
        }

        return $this->vendor_links;
    }

    /**
     * @param  array<int, string>  $scope
     */
    protected function renderNow(string $kind, array $scope): HtmlString
    {
        $injector = $this->app->make(Injector::class);

        $html = $injector->inject($this->placeholder($kind, $scope)->toHtml(), auto: false);

        return new HtmlString($html);
    }

    /**
     * @param  class-string  $class
     * @return array<int, string>
     */
    protected function declaredOn(string $class): array
    {
        $ids = [];

        for ($reflection = new ReflectionClass($class); $reflection; $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getAttributes(RequiresAssets::class) as $attribute) {
                $ids = [...$attribute->newInstance()->assets, ...$ids];
            }
        }

        return $ids;
    }

    /**
     * @return array<int, string>
     */
    protected function expand(string $id): array
    {
        if (str_contains($id, '::')) {
            return [$id];
        }

        if (! isset($this->packages[$id])) {
            throw UnknownAssetException::package($id);
        }

        return array_map(fn (Asset $asset) => $asset->id(), array_values($this->packages[$id]->assets()));
    }

    /**
     * @param  array<string, Asset>  $resolved
     * @param  array<int, string>  $chain
     */
    protected function visit(string $id, array &$resolved, array $chain): void
    {
        if (isset($resolved[$id])) {
            return;
        }

        if (in_array($id, $chain, true)) {
            throw CircularDependencyException::chain([...$chain, $id]);
        }

        $asset = $this->find($id, $chain ? end($chain) : null);

        $chain[] = $id;

        foreach ($asset->dependencies as $dependency) {
            $this->visit($dependency, $resolved, $chain);
        }

        $resolved[$id] = $asset;
    }
}
