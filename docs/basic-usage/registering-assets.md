---
title: Registering assets
sidebar_position: 1
---

A package registers its assets in its service provider. Use the `RegistersAssets` trait and give it the package's name and its `resources` folder:

```php
<?php

namespace Javaabu\Filters;

use Illuminate\Support\ServiceProvider;
use Javaabu\Manifest\Concerns\RegistersAssets;
use Javaabu\Manifest\Package;

class FiltersServiceProvider extends ServiceProvider
{
    use RegistersAssets;

    public function boot(): void
    {
        $this->registerAssets('filters', __DIR__ . '/../resources', function (Package $package) {
            $package
                ->style('filters', 'css/filters.css')
                ->script('filter-bar', 'js/filter-bar.ts', depends_on: ['filters', 'forms::select'])
                ->react('date-range', 'js/date-range.tsx', depends_on: ['filter-bar'])
                ->js()
                ->pages()
                ->npm(['@javaabu/onu' => '^1.0'], peer_dependencies: ['react' => '^19.0']);
        });
    }
}
```

`registerAssets()` works from `register()` or `boot()`, in whatever order the providers load. The callback runs when the manifest is first used. Add `javaabu/manifest` to the package's `require` in `composer.json`.

Without the trait, use the facade from `boot()`:

```php
use Javaabu\Manifest\Facades\Manifest;

Manifest::package('filters', __DIR__ . '/../resources')
    ->style('filters', 'css/filters.css');
```

Calling `Manifest::package('filters')` again, without the path, returns the same package, so you can add more assets later, such as optional ones behind a feature check.

# Names

Assets are namespaced by package: `filters::filter-bar` is the `filter-bar` asset of the `filters` package. Package names are lowercase letters, numbers and dashes. Asset names may also contain dots and slashes (`forms::inputs/select`). Names are unique within a package, so a style and a script can't share one.

# Asset types

The type comes from the entry's extension:

| Method | Extensions | Printed as |
| --- | --- | --- |
| `style($name, $entry)` | `.css`, `.scss`, `.sass` | `<link rel="stylesheet">` in `<head>` |
| `script($name, $entry)` | `.js`, `.mjs`, `.ts`, `.mts`, `.jsx`, `.tsx`, `.vue` | `<script type="module">` before `</body>` |
| `react($name, $entry)` | any script extension | a script, marked as needing React |
| `vue($name, $entry)` | any script extension | a script, marked as needing Vue |

`react()` and `vue()` render the same tag as `script()`. They tell [`manifest:check`](../advanced-usage/building-a-package#npm-dependencies) that the app needs `react`, `react-dom` and `@vitejs/plugin-react`, or `vue` and `@vitejs/plugin-vue`. A Sass style tells it the app needs `sass`.

Entries are paths relative to the folder you passed to `registerAssets()`. Registering a script as a style, or the other way round, throws.

## A script's own CSS

A script can import its CSS:

```ts
// resources/js/select.ts
import "./select.css"
import TomSelect from "tom-select"

document.querySelectorAll<HTMLSelectElement>("[data-select]").forEach((element) => new TomSelect(element))
```

Vite prints the `<link>` for an imported stylesheet next to the script. For CSS that must be in `<head>` before the page paints, register it as its own style and make the script depend on it.

# Dependencies

`depends_on` lists assets that must load first. Bare names are in the same package, and `package::name` can be in any package:

```php
$package
    ->style('base', 'css/base.css')
    ->script('filter-bar', 'js/filter-bar.ts', depends_on: ['base', 'forms::select']);
```

Requiring `filters::filter-bar` loads `filters::base`, then `forms::select` (and whatever it depends on), then `filters::filter-bar`, each once. A dependency on an asset nobody registered, or a cycle, throws a clear error while the page renders.

Dependencies decide the order of tags. A script that imports another package's code (`import { select } from '@javaabu/forms-blade'`) doesn't need a dependency for that: Vite bundles the import. Add one when the order of the tags matters, as with stylesheets or scripts that set up globals.

# Several at once

```php
$package
    ->styles([
        'base' => 'css/base.css',
        'print' => 'css/print.css',
    ])
    ->scripts([
        'filter-bar' => 'js/filter-bar.ts',
        'date-range' => ['js/date-range.ts', ['filter-bar']],
    ]);
```

# HTML attributes

Extra attributes for an asset's tag:

```php
$package->script('editor', 'js/editor.ts', attributes: ['data-turbo-track' => 'reload']);
```

App-wide attributes from `Vite::useScriptTagAttributes()` and `Vite::useStyleTagAttributes()` apply too.

# JS for apps to import

`js()` makes the package's JS folder importable in the app's code, as `@javaabu/{name}`:

```php
$package->js();                          // resources/js as @javaabu/filters
$package->js('ts');                      // resources/ts as @javaabu/filters
$package->js('js', '@acme/filters');     // a different alias
$package->alias('@javaabu/forms-blade'); // only change the alias
```

```ts
import { FilterBar } from "@javaabu/filters"
import { useFilters } from "@javaabu/filters/hooks/use-filters"
```

Pick another alias when `@javaabu/{name}` is also an npm package the app might install. The Vite plugin warns when an alias shadows an installed npm package, and the alias wins. For example, javaabu/forms (Blade) shouldn't use `@javaabu/forms`, which is the React library's npm name.

# Inertia pages

`pages()` registers the folder of the package's Inertia pages (`resources/js/Pages` by default). `resources/js/Pages/Reports/Show.tsx` renders as `filters::Reports/Show`:

```php
$package->pages();
$package->pages('js/Inertia/Pages');
```

See [Inertia pages](./inertia-pages).

# npm dependencies

Package code is built by the app's Vite, so the npm packages it imports must be installed in the app. Declare them:

```php
$package->npm(
    ['tom-select' => '^2.4', '@javaabu/onu' => '^1.0'],
    peer_dependencies: ['react' => '^19.0'],
);
```

Both kinds must be installed by the app. Peer dependencies are the ones that must have a single copy shared with the app (React, Vue). `php artisan manifest:check` tells the app developer what's missing. See [Building a package](../advanced-usage/building-a-package).

Declare what only your Inertia pages (and code apps import through your alias) need with `for: 'pages'`, so Blade apps aren't asked for it. See [Pages-only dependencies](../advanced-usage/building-a-package#pages-only-dependencies).

# Prebuilt assets

`dist()` points at a folder of prebuilt assets, for apps that don't build with Vite:

```php
$package->dist();            // ../dist, next to resources/
$package->dist('../build');
```

See [Prebuilt assets](../advanced-usage/prebuilt-assets).

# Checking what's registered

```bash
php artisan manifest:list
php artisan manifest:list filters   # one package, with its dependencies
php artisan manifest:list --json
```

```
+---------------------+-------+-----------------------------------------------+---------------------------------+
| Asset               | Type  | Entry                                         | Depends on                      |
+---------------------+-------+-----------------------------------------------+---------------------------------+
| widgets::theme      | sass  | vendor/acme/widgets/resources/css/theme.scss  |                                 |
| widgets::core       | js    | vendor/acme/widgets/resources/js/core.js      | widgets::theme                  |
| filters::styles     | css   | vendor/acme/filters/resources/css/filters.css | widgets::theme                  |
| filters::filter-bar | ts    | vendor/acme/filters/resources/js/filter-bar.ts| widgets::core, filters::styles  |
+---------------------+-------+-----------------------------------------------+---------------------------------+
```

Assets are listed in dependency order, and the entry is the file that will be used: an app's override is marked `(override)`.
