---
title: Building a package
sidebar_position: 1
---

This page is the checklist for a package that ships frontend code through manifest. The reference packages are in this repository, in [`tests/Fixtures/app/vendor/acme`](https://github.com/Javaabu/manifest/tree/main/tests/Fixtures/app/vendor/acme): `filters` (Blade components, React, TypeScript, Inertia pages) and `widgets` (Sass, plain JS, Vue, prebuilt dist).

# Layout

```
filters/
├── composer.json            requires javaabu/manifest
├── package.json             dev tooling only (vite, typescript, tests)
├── vite.config.js           optional: builds dist/ with packageBuild()
├── dist/                    optional: prebuilt assets, committed or built on release
├── resources/
│   ├── css/filters.css
│   ├── js/
│   │   ├── index.ts         what apps import as @javaabu/filters
│   │   ├── filter-bar.ts    a Blade entry
│   │   ├── components/FilterBar.tsx
│   │   └── Pages/           Inertia pages: filters::Index
│   └── views/components/filter-bar.blade.php
└── src/
    ├── FiltersServiceProvider.php
    └── Components/FilterBar.php
```

# 1. Register

```php
<?php

namespace Javaabu\Filters;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Javaabu\Manifest\Concerns\RegistersAssets;
use Javaabu\Manifest\Package;

class FiltersServiceProvider extends ServiceProvider
{
    use RegistersAssets;

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filters');

        Blade::componentNamespace('Javaabu\\Filters\\Components', 'filters');

        $this->registerAssets('filters', __DIR__ . '/../resources', function (Package $package) {
            $package
                ->style('filters', 'css/filters.css')
                ->script('filter-bar', 'js/filter-bar.ts', depends_on: ['filters'])
                ->js()
                ->pages()
                ->dist()
                ->npm(['@javaabu/onu' => '^1.0'], peer_dependencies: ['react' => '^19.0', 'react-dom' => '^19.0']);
        });
    }
}
```

See [Registering assets](../basic-usage/registering-assets) for every method.

# 2. Declare what components need

```php
#[RequiresAssets('filters::filter-bar')]
class FilterBar extends Component {}
```

See [Loading assets in Blade](../basic-usage/loading-assets).

# 3. npm dependencies

Your JS and CSS are built by the **app's** Vite, from the app's `node_modules`. Every npm package your code imports must be declared:

```php
$package->npm(
    ['tom-select' => '^2.4'],                         // the app installs these
    peer_dependencies: ['react' => '^19.0'],          // and these, shared with its own code
);
```

Ranges use npm syntax (`^`, `~`, `>=`, `||`, x-ranges, hyphen ranges). Git and `file:` dependencies (`github:Javaabu/react-forms#main`) are fine; their version isn't checked.

## Pages-only dependencies

A Blade app builds your registered assets, but never your Inertia pages or the code apps import through your alias. Declare what only those need with `for: 'pages'`, so Blade apps aren't asked to install it:

```php
$package
    ->npm(['tom-select' => '^2.4'])                    // the registered assets import these
    ->npm(
        ['@javaabu/forms' => 'github:Javaabu/react-forms#main', 'recharts' => '^3.0'],
        peer_dependencies: ['react' => '^19.0', '@inertiajs/react' => '^2.0 || ^3.0'],
        for: 'pages',                                   // only pages and alias imports use these
    );
```

`for` takes `'assets'` (the default) or `'pages'`, or the `NpmScopes` enum. `@inertiajs/*` packages always count as pages, wherever they're declared. If a registered asset imports something (a Blade island that renders React, say), it belongs in the assets.

The app developer runs:

```bash
php artisan manifest:check
```

It lists every npm package the app's Laravel packages need: their `npm()` dependencies, `sass` for Sass styles, React or Vue and their Vite plugins for `react()` / `vue()` assets, and `vite` and `laravel-vite-plugin`. It checks what the registered assets need, and what pages need when the app's `package.json` has an Inertia adapter (`@inertiajs/react`, `@inertiajs/vue3`, …). So a Blade app isn't asked for packages only Inertia pages use. Choose yourself with `--scope=assets`, `--scope=pages` or both. Each is checked against `package.json` and the installed version in `node_modules`, and the command prints the install command for the app's package manager (npm, pnpm, Yarn or Bun). It exits with an error when something is missing, so it can run in CI.

Keep your package's own `package.json` for developing the package: Vite, TypeScript, and the dependencies above as dev dependencies so your tests and builds work. Apps never install it.

# 4. TypeScript

Write `resources/js` as if it were in the app: relative imports for your own code, your alias (`@javaabu/filters/...`) if you prefer, and bare imports for npm packages. In your package's `tsconfig.json`, map your own alias:

```json
{
  "compilerOptions": {
    "paths": {
      "@javaabu/filters": ["./resources/js"],
      "@javaabu/filters/*": ["./resources/js/*"]
    }
  },
  "include": ["resources/js"]
}
```

Apps get the same mapping from `php artisan manifest:types`.

# 5. A Vite plugin (optional)

If your package needs build-time behaviour (virtual modules, files generated from PHP, HMR for something that isn't a module), ship a Vite plugin and register it. The app's `manifest()` runs it, so apps don't add a line to `vite.config.js` for your package:

```php
$package->vitePlugin('../dist/vite.js', ['watch' => ['lang']]);
```

- The module is relative to the folder passed to `registerAssets()`, like `dist()`. It must be **built JavaScript** (`.js` or `.mjs`), since it's imported by Node when Vite loads the app's config: commit it, as manifest commits its own `dist/`. A `.js` file needs a `package.json` with `"type": "module"` next to it or above it in your package (don't `export-ignore` it), or use `.mjs`.
- Its default export is a factory, called with the options: a plugin, an array of plugins, or a promise of either.
- The options must be JSON-serialisable: they go through `bootstrap/cache/manifest.json`. Apps can merge their own over them, or turn your plugin off, with `manifest({ plugins: { "your-package": { ... } | false } })`.
- Plugins run after manifest's own plugin, in package order. They run in every Vite command, like any plugin in `vite.config.js`: use `apply` or the hooks you need.

```js
// js/src/vite.ts, built to dist/vite.js
import type { Plugin } from "vite"

export interface LocalizationPluginOptions {
  watch?: string[]
}

export default function localization(options: LocalizationPluginOptions = {}): Plugin {
  return {
    name: "javaabu-localization",
    configureServer(server) {
      /* ... */
    },
  }
}
```

Keep the factory usable on its own too (`plugins: [manifest({ plugins: { localization: false } }), localization({ ... })]`), for apps that want to configure it in code. If an app adds your plugin to `vite.config.js` as well, `manifest()` warns that it runs twice.

# 6. Prebuilt assets (optional)

To support apps that don't build with Vite, ship a `dist` built with the preset:

```js
// vite.config.js in the package
import react from "@vitejs/plugin-react"
import { defineConfig } from "vite"
import { packageBuild } from "./vendor/javaabu/manifest/dist/package.js"

export default defineConfig({
  ...packageBuild({ input: ["css/filters.css", "js/filter-bar.ts"] }),
  plugins: [react()],
})
```

```bash
npx vite build   # writes dist/ and dist/.vite/manifest.json
```

The `input` paths are the entries you register. Register the folder with `->dist()`. See [Prebuilt assets](./prebuilt-assets).

# 7. Test

Use [`Manifest::fake()`](./testing) in your package's tests to check what your components require without a Vite build. Use [Testbench](https://packages.tools/testbench) to run `php artisan manifest:list` and `manifest:check` against your package:

```bash
vendor/bin/testbench manifest:list
```

# 8. Document for app developers

Tell them, in your docs:

- to add the Vite plugin, if your package has no `dist`,
- to run `php artisan manifest:check`,
- the asset names, so they can place them with `@manifestScripts('filters')` or require them in their own views,
- the Inertia page names, if you ship pages,
- which files are meant to be overridden.
