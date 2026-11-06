---
title: API reference
sidebar_position: 7
---

# PHP

## `Javaabu\Manifest\Concerns\RegistersAssets`

For service providers.

| Method | |
| --- | --- |
| `registerAssets(string $name, string $path, Closure $callback): void` | Registers the package `$name` with its resources folder `$path`. The callback gets the `Package` and runs once, when the manifest is first used. Works from `register()` or `boot()`. |

## `Javaabu\Manifest\Package`

Returned by `Manifest::package()` and passed to `registerAssets()`. Every setter returns the package.

| Method | |
| --- | --- |
| `style($name, $entry, array $depends_on = [], array $attributes = [])` | A `.css`, `.scss` or `.sass` entry |
| `script($name, $entry, array $depends_on = [], array $attributes = [])` | A `.js`, `.mjs`, `.ts`, `.mts`, `.jsx`, `.tsx` or `.vue` entry |
| `react(...)`, `vue(...)` | A script that needs React or Vue |
| `styles(array $styles)`, `scripts(array $scripts)` | `name => entry` or `name => [entry, [dependencies]]` |
| `js(string $path = 'js', ?string $alias = null)` | The JS folder apps import as the alias |
| `alias(string $alias)` | The import alias (default `@javaabu/{name}`) |
| `pages(string $path = 'js/Pages')` | The Inertia pages folder |
| `dist(string $path = '../dist')` | The prebuilt assets folder |
| `npm(array $dependencies = [], array $peer_dependencies = [], NpmScopes\|string $for = 'assets')` | npm packages the app must install, `name => range`: for the registered assets, or (`'pages'`) only for Inertia pages and alias imports |
| `name()`, `path(?string $relative = null)` | The package name, and its resources folder (or a path in it) |
| `assets()`, `asset(string $name)` | The registered `Asset`s |
| `jsPath()`, `importAlias()`, `pagesPath()`, `distPath()`, `npmDependencies()`, `npmPeerDependencies()`, `assetFolders()` | What was registered |

## `Javaabu\Manifest\Facades\Manifest`

The facade for `Javaabu\Manifest\Manifest`, a singleton.

| Method | |
| --- | --- |
| `package(string $name, ?string $path = null): Package` | Registers a package, or returns a registered one |
| `hasPackage(string $name): bool`, `packages(): array` | Registered packages |
| `has(string $id): bool`, `find(string $id): Asset` | An asset by `package::name`. `find()` throws `UnknownAssetException` |
| `all(): array` | Every asset, in dependency order |
| `resolve(array $ids): array` | Assets and package names expanded with their dependencies, in order |
| `require(string\|array ...$ids): Manifest` | Load assets on the current page |
| `isRequired(string $id): bool`, `required(): array` | What the current request requires |
| `renderStyles(array $scope = []): HtmlString`, `renderScripts(array $scope = []): HtmlString` | Print required tags now, for views rendered outside a request |
| `pages(): array` | Every package Inertia page, `package::Page => file`, overrides applied |
| `overridePath(string $package, ?string $relative = null): string` | Where the app overrides a package's file |
| `resolvePath(string $package, string $relative): string` | The override if it exists, otherwise the package's file |
| `entryPath(Asset $asset): string`, `isOverridden(Asset $asset): bool` | An asset's entry, overrides applied |
| `distPath(string $package): ?string`, `distUrlPath(string $package): string` | A package's prebuilt folder and its URL path |
| `relativeToBase(string $path): string` | A path relative to the app root, as Vite uses it |
| `fake(): ManifestFake` | See [Testing](./testing) |

## `Javaabu\Manifest\Asset`

A read-only value: `package`, `name`, `entry`, `type` (`AssetTypes`), `dependencies` (ids), `attributes`. Methods: `id()`, `isStyle()`, `isScript()`, `toArray()`, and the static `makeId($package, $name)` and `parseId($id)`.

## `Javaabu\Manifest\Enums\AssetTypes`

`CSS`, `SASS`, `JS`, `TS`, `REACT`, `VUE`. Methods: `fromPath($path)`, `isStyle()`, `isScript()`, `buildRequirements()` (npm packages needed to build it).

## Components

| | |
| --- | --- |
| `#[Javaabu\Manifest\Attributes\RequiresAssets(...$ids)]` | On a component class. Repeatable, inherited. |
| `Javaabu\Manifest\Contracts\DeclaresAssets` | `requiredAssets(): array`, for assets that depend on props |

## Blade directives

`@manifestStyles(...$scope)`, `@manifestScripts(...$scope)`, `@manifestRequire(...$ids)`. See [Loading assets in Blade](../basic-usage/loading-assets).

## Testing

`Javaabu\Manifest\Testing\ManifestFake`: `assertAssetRequired()`, `assertAssetNotRequired()`, `assertNothingRequired()`, `assertAssetInjected()`, `assertAssetNotInjected()`, `requiredAssets()`, `injectedAssets()`.

## Exceptions

| | Thrown when |
| --- | --- |
| `Javaabu\Manifest\Exceptions\InvalidAssetException` | registering an invalid name, a duplicate, an unsupported extension, or a style as a script |
| `Javaabu\Manifest\Exceptions\UnknownAssetException` | requiring or depending on an asset or package nobody registered |
| `Javaabu\Manifest\Exceptions\CircularDependencyException` | dependencies form a cycle; the message shows it |

## HTTP

| | |
| --- | --- |
| `Javaabu\Manifest\Http\Middleware\InjectAssets` | Global middleware that fills directives and injects tags |
| Route `manifest.dist` | `GET /{dist_url}/{package}/{path}`, prebuilt files |

# JavaScript

Everything ships in `vendor/javaabu/manifest/dist`. Import it by path, or as `@javaabu/manifest/*` with `"@javaabu/manifest": "file:vendor/javaabu/manifest"` in `package.json`. The Vite plugin also resolves `@javaabu/manifest/inertia` itself.

## `@javaabu/manifest/vite` (`dist/vite.js`)

| Export | |
| --- | --- |
| `default`, `manifest(options?: ManifestPluginOptions): ManifestPlugin` | The Vite plugin. Options: `discover`, `discovery`, `input`. See [Vite](../basic-usage/vite#options). |
| `ManifestPlugin.inputs(): string[]` | Every registered asset's entry, relative to the Vite root |
| `ManifestPlugin.packages(): ResolvedPackage[]` | The discovered packages |
| `computeInputs()`, `mergeInputs()`, `rewriteManifestKeys()` | The building blocks, for other tooling |
| types `Discovery`, `DiscoveredPackage`, `DiscoveredAsset`, `ResolvedPackage` | The discovery file's shape |

## `@javaabu/manifest/inertia` (`dist/inertia.js`)

| Export | |
| --- | --- |
| `resolvePage<T>(name, pages?, options?): Promise<T>` | Resolve any Inertia page name. `pages` is an `import.meta.glob()` result or a resolver function; `options` takes `base` and `extensions`. |
| `resolvePackagePage<T>(package, page, options?): Promise<T>` | Resolve a package page |
| `isPackagePage(name)`, `parsePageName(name)`, `packagePageNames()` | Page names |
| `packagePage(name)` | A typed page name |
| `PACKAGE_SEPARATOR` | `"::"` |
| types `PackagePages`, `PackagePageName`, `PackagePageModule<K>`, `PageGlob<T>`, `PageResolver<T>`, `ResolvePageOptions` | See [TypeScript](./typescript) |

It needs the Vite plugin, which provides the `virtual:javaabu-manifest/pages` module it reads.

## `@javaabu/manifest/package` (`dist/package.js`)

| Export | |
| --- | --- |
| `packageBuild(options: PackageBuildOptions): UserConfig` | A Vite config that builds a package's `dist`. Options: `input` (required), `resources` (`"resources"`), `outDir` (`"dist"`), `external` (`[]`), `root` (`process.cwd()`). |
