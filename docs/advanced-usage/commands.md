---
title: Commands
sidebar_position: 6
---

| Command | What it does |
| --- | --- |
| `php artisan manifest:list [package] [--json]` | Lists registered assets in dependency order, with their type, the entry that will be used (overrides marked) and dependencies. With a package, lists that package's assets and what they depend on. |
| `php artisan manifest:check [--scope=assets] [--scope=pages] [--json]` | Checks that the app has the npm packages its Laravel packages need, and prints the install command. Exits with an error when something is missing or the wrong version. Checks the registered assets' needs, and pages' needs when the app has an Inertia adapter in `package.json`; `--scope` chooses instead. |
| `php artisan manifest:discover` | Writes the registered packages and assets to `bootstrap/cache/manifest.json` for the Vite plugin. Runs by itself when Vite starts and after `package:discover`. |
| `php artisan manifest:types [--path=] [--no-tsconfig]` | Adds the packages' import paths to `tsconfig.json`, and writes types for their Inertia pages. See [TypeScript](./typescript). |
| `php artisan manifest:override {asset or package} [path] [--force]` | Copies one package file into `resources/vendor/{package}`, where it overrides the package's. `manifest:override filters::filter-bar` copies an asset's entry; `manifest:override filters js/components/FilterBar.tsx` copies any file. |

# Publishing tags

| Tag | Publishes |
| --- | --- |
| `manifest-config` | `config/manifest.php` |
| `manifest-translations` | `lang/vendor/manifest` |
| `manifest-assets`, `manifest-assets-{package}` | packages' asset folders to `resources/vendor/{package}`, as overrides |
| `manifest-dist`, `manifest-dist-{package}` | packages' prebuilt files to `public/vendor/manifest/{package}` |

```bash
php artisan vendor:publish --tag=manifest-dist --force
```

# Composer scripts

Nothing is needed for discovery: it runs after `package:discover`, which Laravel's default `post-autoload-dump` script already runs. To publish prebuilt files on every update:

```json
"post-update-cmd": [
    "@php artisan vendor:publish --tag=manifest-dist --ansi --force"
]
```
