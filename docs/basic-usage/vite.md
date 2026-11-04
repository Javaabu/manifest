---
title: Vite
sidebar_position: 3
---

The Vite plugin builds packages' assets with the app's own Vite, and lets the app's code import packages' JavaScript. It ships inside the Composer package, at `vendor/javaabu/manifest/dist/vite.js`.

```js
// vite.config.js
import react from "@vitejs/plugin-react"
import vue from "@vitejs/plugin-vue"
import laravel from "laravel-vite-plugin"
import { defineConfig } from "vite"
import manifest from "./vendor/javaabu/manifest/dist/vite.js"

export default defineConfig({
  plugins: [
    laravel({ input: ["resources/css/app.css", "resources/js/app.tsx"], refresh: true }),
    manifest(),
    react(),
    vue(),
  ],
})
```

# What it does

- **Finds the packages.** On start it runs `php artisan manifest:discover`, which writes every registered package and asset to `bootstrap/cache/manifest.json`.
- **Builds every registered asset.** Each style and script entry is added to the build's inputs, so `npm run build` puts them in `public/build/manifest.json`, where Laravel looks for them. Your own `input` can be a string, an array or an object; the entries are added to it. SSR builds (`vite build --ssr`) are left alone.
- **Serves them in development.** With `npm run dev`, Laravel points the tags at the dev server, and package files hot reload like your own. The plugin allows Vite to serve package folders, including packages symlinked from outside the app.
- **Resolves package imports.** `@javaabu/filters` and `@javaabu/filters/...` resolve to the package's JS folder (see [`js()`](./registering-assets#js-for-apps-to-import)).
- **Applies overrides.** An app's copy of a package file in `resources/vendor/{package}/...` is used wherever the package uses that file. See [Overriding assets](./overriding-assets).
- **Shares the app's npm packages.** Bare imports in package files (`react`, `vue`, `@javaabu/onu`) resolve from the app's `node_modules`, so there's a single copy of React or Vue even when a package lives outside the app.
- **Provides `@javaabu/manifest/inertia`** for [Inertia pages](./inertia-pages).

# Options

```js
manifest({
  discover: true,
  discovery: "bootstrap/cache/manifest.json",
  input: true,
})
```

| Option | Default | What it does |
| --- | --- | --- |
| `discover` | `true` | Run `php artisan manifest:discover` when Vite starts. A string runs that command instead, in the Vite root, such as `"sail artisan manifest:discover"` or `"docker compose exec -T app php artisan manifest:discover"`. `false` only reads the existing file. When the command fails, the plugin falls back to the existing file with a warning, or stops with an error if there isn't one. |
| `discovery` | `"bootstrap/cache/manifest.json"` | The discovery file, relative to the Vite root. Change it together with `manifest.discovery_path`. |
| `input` | `true` | Add every registered asset to the build. With `false`, list them yourself: the plugin object's `inputs()` returns them. |

```js
const assets = manifest({ input: false })

export default defineConfig({
  plugins: [laravel({ input: ["resources/js/app.js", ...assets.inputs()] }), assets],
})
```

`inputs()` runs discovery from the current directory if Vite hasn't yet.

# Discovery

```bash
php artisan manifest:discover
```

It runs by itself:

- when Vite starts (the `discover` option),
- after `php artisan package:discover`, which Laravel apps run on every `composer install`, `update` and `dump-autoload`. It runs in a fresh process, so newly installed packages are included.

Run it by hand if you build without PHP (copy `bootstrap/cache/manifest.json` into the build, and set `discover: false`).

Paths in the file are relative to the app's root, so it works when PHP runs in a container and Node on the host.

# Docker and Sail

When PHP only runs in a container, tell the plugin how to run artisan:

```js
manifest({ discover: "./vendor/bin/sail artisan manifest:discover" })
```

# Path repositories

Packages installed from a Composer `path` repository are symlinked into `vendor/`. The plugin handles that: it resolves the package's imports from the app, allows Vite to serve the real folder, and writes the build manifest with the `vendor/...` paths Laravel looks up.

# Without Vite

Apps that don't build with Vite, or that are missing a package's asset in their build, use the package's [prebuilt assets](../advanced-usage/prebuilt-assets) when the package has them.
