<?php

namespace Javaabu\Manifest;

use Javaabu\Manifest\Enums\AssetTypes;
use Javaabu\Manifest\Enums\NpmScopes;
use Javaabu\Manifest\Exceptions\InvalidAssetException;

class Package
{
    /** @var array<string, Asset> */
    protected array $assets = [];

    protected ?string $js_path = null;

    protected ?string $alias = null;

    protected ?string $pages_path = null;

    protected ?string $dist_path = null;

    /** @var array<string, array<string, string>> scope => name => constraint */
    protected array $npm_dependencies = [];

    /** @var array<string, array<string, string>> scope => name => constraint */
    protected array $npm_peer_dependencies = [];

    public function __construct(
        protected string $name,
        protected string $path,
    ) {
        if (! preg_match('/^[a-z0-9][a-z0-9-]*$/', $name)) {
            throw InvalidAssetException::invalidPackageName($name);
        }

        $this->path = rtrim(realpath($path) ?: $path, '/\\');
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * The package's resources folder. Entries are relative to it.
     */
    public function path(?string $relative = null): string
    {
        if (! $relative) {
            return $this->path;
        }

        return $this->path . '/' . ltrim($relative, '/');
    }

    /**
     * Register a stylesheet (.css, .scss or .sass).
     *
     * @param  array<int, string>  $depends_on
     * @param  array<string, string|bool>  $attributes
     */
    public function style(string $name, string $entry, array $depends_on = [], array $attributes = []): static
    {
        $type = AssetTypes::fromPath($entry);

        if (! $type->isStyle()) {
            throw InvalidAssetException::notAStyle(Asset::makeId($this->name, $name), $entry);
        }

        return $this->add($name, $entry, $type, $depends_on, $attributes);
    }

    /**
     * Register a script entry (.js, .ts, .jsx, .tsx or .vue).
     *
     * @param  array<int, string>  $depends_on
     * @param  array<string, string|bool>  $attributes
     */
    public function script(string $name, string $entry, array $depends_on = [], array $attributes = []): static
    {
        return $this->add($name, $entry, $this->scriptType($name, $entry), $depends_on, $attributes);
    }

    /**
     * Register a script entry that renders React components.
     *
     * @param  array<int, string>  $depends_on
     * @param  array<string, string|bool>  $attributes
     */
    public function react(string $name, string $entry, array $depends_on = [], array $attributes = []): static
    {
        $this->scriptType($name, $entry);

        return $this->add($name, $entry, AssetTypes::REACT, $depends_on, $attributes);
    }

    /**
     * Register a script entry that renders Vue components.
     *
     * @param  array<int, string>  $depends_on
     * @param  array<string, string|bool>  $attributes
     */
    public function vue(string $name, string $entry, array $depends_on = [], array $attributes = []): static
    {
        $this->scriptType($name, $entry);

        return $this->add($name, $entry, AssetTypes::VUE, $depends_on, $attributes);
    }

    /**
     * Register several stylesheets at once, as name => entry, or
     * name => [entry, [dependencies]].
     *
     * @param  array<string, string|array{0: string, 1?: array<int, string>}>  $styles
     */
    public function styles(array $styles): static
    {
        foreach ($styles as $name => $definition) {
            [$entry, $depends_on] = $this->parseDefinition($definition);

            $this->style($name, $entry, $depends_on);
        }

        return $this;
    }

    /**
     * Register several scripts at once, as name => entry, or
     * name => [entry, [dependencies]].
     *
     * @param  array<string, string|array{0: string, 1?: array<int, string>}>  $scripts
     */
    public function scripts(array $scripts): static
    {
        foreach ($scripts as $name => $definition) {
            [$entry, $depends_on] = $this->parseDefinition($definition);

            $this->script($name, $entry, $depends_on);
        }

        return $this;
    }

    /**
     * The folder with the package's JS and TS source, which apps import as
     * the package's alias (@javaabu/{name} by default).
     */
    public function js(string $path = 'js', ?string $alias = null): static
    {
        $this->js_path = trim($path, '/');

        if ($alias) {
            $this->alias($alias);
        }

        return $this;
    }

    /**
     * The import alias for the package's JS folder.
     */
    public function alias(string $alias): static
    {
        $this->alias = $alias;

        $this->js_path ??= 'js';

        return $this;
    }

    /**
     * The folder with the package's Inertia pages, rendered as {name}::Page.
     */
    public function pages(string $path = 'js/Pages'): static
    {
        $this->pages_path = trim($path, '/');

        return $this;
    }

    /**
     * The folder with the package's prebuilt assets, used by apps that don't
     * build with Vite. It must contain a Vite manifest (.vite/manifest.json).
     */
    public function dist(string $path = '../dist'): static
    {
        $this->dist_path = trim($path, '/');

        return $this;
    }

    /**
     * npm packages the app must install to build this package's assets, or
     * (`for: 'pages'`) its Inertia pages and the JS apps import through its
     * alias. `@inertiajs/*` packages always count as pages.
     *
     * @param  array<string, string>  $dependencies  name => version constraint
     * @param  array<string, string>  $peer_dependencies  kept out of prebuilt bundles
     */
    public function npm(array $dependencies = [], array $peer_dependencies = [], NpmScopes|string $for = NpmScopes::ASSETS): static
    {
        $scope = NpmScopes::fromValue($for);

        foreach ($dependencies as $name => $constraint) {
            $this->npm_dependencies[NpmScopes::forDependency($name, $scope)->value][$name] = $constraint;
        }

        foreach ($peer_dependencies as $name => $constraint) {
            $this->npm_peer_dependencies[NpmScopes::forDependency($name, $scope)->value][$name] = $constraint;
        }

        return $this;
    }

    /** @return array<string, Asset> */
    public function assets(): array
    {
        return $this->assets;
    }

    public function asset(string $name): ?Asset
    {
        return $this->assets[$name] ?? null;
    }

    public function jsPath(): ?string
    {
        return $this->js_path;
    }

    public function importAlias(): ?string
    {
        if (! $this->js_path) {
            return null;
        }

        return $this->alias ?? "@javaabu/{$this->name}";
    }

    public function pagesPath(): ?string
    {
        return $this->pages_path;
    }

    public function distPath(): ?string
    {
        return $this->dist_path;
    }

    /**
     * The top-level folders of resources/ that hold this package's assets,
     * which publishing copies: the JS folder and every entry's folder.
     *
     * @return array<int, string>
     */
    public function assetFolders(): array
    {
        $paths = array_map(fn (Asset $asset) => $asset->entry, $this->assets);

        foreach ([$this->js_path, $this->pages_path] as $path) {
            if ($path) {
                $paths[] = "{$path}/";
            }
        }

        $folders = [];

        foreach ($paths as $path) {
            $segments = explode('/', $path);

            if (count($segments) > 1 && $segments[0] !== '' && $segments[0] !== '..') {
                $folders[$segments[0]] = true;
            }
        }

        return array_keys($folders);
    }

    /**
     * Every scope's, or one scope's, npm dependencies.
     *
     * @return array<string, string>
     */
    public function npmDependencies(?NpmScopes $scope = null): array
    {
        return $this->scopedNpm($this->npm_dependencies, $scope);
    }

    /**
     * Every scope's, or one scope's, npm peer dependencies.
     *
     * @return array<string, string>
     */
    public function npmPeerDependencies(?NpmScopes $scope = null): array
    {
        return $this->scopedNpm($this->npm_peer_dependencies, $scope);
    }

    /**
     * @param  array<string, array<string, string>>  $dependencies
     * @return array<string, string>
     */
    protected function scopedNpm(array $dependencies, ?NpmScopes $scope): array
    {
        if ($scope) {
            return $dependencies[$scope->value] ?? [];
        }

        $all = [];

        foreach (NpmScopes::cases() as $case) {
            $all = [...$all, ...($dependencies[$case->value] ?? [])];
        }

        return $all;
    }

    /**
     * @param  array<int, string>  $depends_on
     * @param  array<string, string|bool>  $attributes
     */
    protected function add(string $name, string $entry, AssetTypes $type, array $depends_on, array $attributes): static
    {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', $name)) {
            throw InvalidAssetException::invalidName($name);
        }

        if (isset($this->assets[$name])) {
            throw InvalidAssetException::duplicate(Asset::makeId($this->name, $name));
        }

        $dependencies = array_map(function (string $dependency) {
            [$package, $asset] = Asset::parseId($dependency, $this->name);

            return Asset::makeId($package, $asset);
        }, array_values($depends_on));

        $this->assets[$name] = new Asset(
            package: $this->name,
            name: $name,
            entry: ltrim($entry, '/'),
            type: $type,
            dependencies: array_values(array_unique($dependencies)),
            attributes: $attributes,
        );

        return $this;
    }

    protected function scriptType(string $name, string $entry): AssetTypes
    {
        $type = AssetTypes::fromPath($entry);

        if (! $type->isScript()) {
            throw InvalidAssetException::notAScript(Asset::makeId($this->name, $name), $entry);
        }

        return $type;
    }

    /**
     * @param  string|array{0: string, 1?: array<int, string>}  $definition
     * @return array{0: string, 1: array<int, string>}
     */
    protected function parseDefinition(string|array $definition): array
    {
        if (is_string($definition)) {
            return [$definition, []];
        }

        return [$definition[0], $definition[1] ?? []];
    }
}
