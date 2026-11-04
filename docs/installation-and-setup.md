---
title: Installation & Setup
sidebar_position: 1.2
---

Packages that ship assets usually require `javaabu/manifest` themselves, so it's often installed already. To install it directly:

```bash
composer require javaabu/manifest
```

That's enough for Blade apps whose packages ship prebuilt assets: the required styles and scripts are injected into your pages automatically. To build packages' assets with your own Vite (recommended), add the Vite plugin.

# Add the Vite plugin

Import the plugin from the Composer package and add it next to `laravel()`:

```js
// vite.config.js
import laravel from "laravel-vite-plugin"
import { defineConfig } from "vite"
import manifest from "./vendor/javaabu/manifest/dist/vite.js"

export default defineConfig({
  plugins: [
    laravel({
      input: ["resources/css/app.css", "resources/js/app.js"],
      refresh: true,
    }),
    manifest(),
  ],
})
```

Then check that you have every npm package the Laravel packages need, and install what's missing:

```bash
php artisan manifest:check
```

```
+---------------------+-------+-----------+-----------+---------------------------+
| npm package         | Needs | Needed by | Installed | Status                    |
+---------------------+-------+-----------+-----------+---------------------------+
| @vitejs/plugin-react|       | filters   | -         | Missing from package.json |
| react               | ^19.0 | filters   | 19.3.1    | OK                        |
...
  npm install -D @vitejs/plugin-react
```

Add the framework plugins your packages use (`react()`, `vue()`) to `vite.config.js` as usual. Run `npm run dev` or `npm run build` as you always do: every package's styles and scripts are part of the build.

The plugin runs `php artisan manifest:discover` when Vite starts, to find out what packages have registered. The same command also runs after every `composer install`, `update` and `dump-autoload`. If PHP isn't available where Vite runs (a Node-only CI step, say), the plugin uses the last `bootstrap/cache/manifest.json` instead. See [Vite](./basic-usage/vite) for the options.

If you'd rather import the plugin by package name, add the Composer package as a local npm dependency:

```json
"devDependencies": {
  "@javaabu/manifest": "file:vendor/javaabu/manifest"
}
```

```js
import manifest from "@javaabu/manifest/vite"
```

# Inertia apps

Resolve pages with `resolvePage()`, so `Inertia::render('filters::Index')` finds the package's page:

```tsx
// resources/js/app.tsx
import { createInertiaApp } from "@inertiajs/react"
import { resolvePage } from "@javaabu/manifest/inertia"
import { createRoot } from "react-dom/client"

createInertiaApp({
  resolve: (name) => resolvePage(name, import.meta.glob("./pages/**/*.tsx")),
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />)
  },
})
```

The Vite plugin resolves `@javaabu/manifest/inertia` itself, so there's nothing to install. See [Inertia pages](./basic-usage/inertia-pages) for Vue and server rendering.

# TypeScript

Let TypeScript know about the packages' import aliases and pages:

```bash
php artisan manifest:types
```

This adds a `paths` entry for each package (`@javaabu/filters` → `./vendor/javaabu/filters/resources/js`) to `tsconfig.json`, keeping your own paths. It also writes `resources/js/types/manifest.d.ts` with a type for every package page. Run it again after installing or updating packages. See [TypeScript](./advanced-usage/typescript).

# Publishing the config file

Publishing the config file is optional:

```bash
php artisan vendor:publish --tag="manifest-config"
```

This is the default content of the config file:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Automatic injection
    |--------------------------------------------------------------------------
    |
    | When enabled, the styles and scripts required while rendering a page are
    | injected into HTML responses: styles before </head>, scripts before
    | </body>. Assets placed with the Blade directives are never injected
    | a second time.
    |
    */

    'inject' => true,

    /*
    |--------------------------------------------------------------------------
    | Overrides
    |--------------------------------------------------------------------------
    |
    | Apps override a package's resources file by file: a file at
    | {overrides_path}/{package}/js/FilterBar.tsx is used instead of the
    | package's resources/js/FilterBar.tsx. Defaults to resources/vendor.
    |
    */

    'overrides_path' => null,

    /*
    |--------------------------------------------------------------------------
    | Discovery file
    |--------------------------------------------------------------------------
    |
    | php artisan manifest:discover writes the registered packages and assets
    | here for the Vite plugin. It runs again after package:discover (so on
    | composer install and dump-autoload) and when Vite starts. Defaults to
    | bootstrap/cache/manifest.json.
    |
    */

    'discovery_path' => null,

    /*
    |--------------------------------------------------------------------------
    | Prebuilt assets
    |--------------------------------------------------------------------------
    |
    | Packages can ship prebuilt assets (->dist()) for apps that don't build
    | them with Vite. "auto" uses them for assets missing from the app's Vite
    | build, "always" uses them whenever a package has them, and "never"
    | always uses the app's Vite. Overridden assets always use the app's Vite.
    |
    | They're served from /{dist_url}/{package}: from public/ once published
    | (vendor:publish --tag=manifest-dist), through a cached route until then.
    |
    */

    'dist' => 'auto',

    'dist_url' => 'vendor/manifest',

];
```

Every option is explained in [Configuration](./advanced-usage/configuration).

# Translations

Command output is translated into English and Dhivehi. To change the wording, publish the translations:

```bash
php artisan vendor:publish --tag="manifest-translations"
```
