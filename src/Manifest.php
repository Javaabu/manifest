<?php

namespace Javaabu\Manifest;

use Illuminate\Contracts\Foundation\Application;
use Javaabu\Manifest\Exceptions\CircularDependencyException;
use Javaabu\Manifest\Exceptions\UnknownAssetException;

class Manifest
{
    /** @var array<string, Package> */
    protected array $packages = [];

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

    public function isOverridden(Asset $asset): bool
    {
        return is_file($this->overridePath($asset->package, $asset->entry));
    }

    /**
     * A path relative to the app's root, as Vite inputs and the Vite
     * manifest expect.
     */
    public function relativeToBase(string $path): string
    {
        $base = rtrim($this->app->basePath(), '/\\') . '/';

        $path = str_replace('\\', '/', $path);
        $base = str_replace('\\', '/', $base);

        if (str_starts_with($path, $base)) {
            return substr($path, strlen($base));
        }

        return $path;
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
