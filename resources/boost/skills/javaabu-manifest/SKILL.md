---
name: javaabu-manifest
description: Ship and use frontend assets of Laravel packages with javaabu/manifest. Use when a Laravel package registers CSS, Sass, JS, TS, React or Vue assets or Inertia pages (registerAssets, RequiresAssets, @manifestRequire), when an app loads package assets (@manifestStyles, @manifestScripts, the Vite plugin in vite.config.js, resolvePage for package::Page Inertia pages), when overriding a package's JS/CSS/page in resources/vendor, or when fixing missing package assets, npm dependencies (manifest:check) or TypeScript paths (manifest:types).
license: MIT
metadata:
  author: Javaabu
---

# Package assets with javaabu/manifest

The docs ship with the package: `vendor/javaabu/manifest/llms.txt` lists every page in `vendor/javaabu/manifest/docs/`.

## Which side are you on?

| Task | Read |
| --- | --- |
| An app uses a package's components, pages or JS | This file |
| Writing a package that ships CSS/JS/React/Vue or Inertia pages | This file, then [references/package-authoring.md](references/package-authoring.md) |
| Assets missing, wrong order, 404s, Vite errors | [references/troubleshooting.md](references/troubleshooting.md) |

## In an app

Setup is three steps, once:

```js
// vite.config.js
import laravel from "laravel-vite-plugin"
import react from "@vitejs/plugin-react"
import { defineConfig } from "vite"
import manifest from "./vendor/javaabu/manifest/dist/vite.js"

export default defineConfig({
  plugins: [laravel({ input: ["resources/css/app.css", "resources/js/app.tsx"], refresh: true }), manifest(), react()],
})
```

```bash
php artisan manifest:check   # then run the install command it prints
php artisan manifest:types   # TypeScript apps: tsconfig paths + package page types
```

Then:

- **Blade:** using a package component is enough (`<x-filters::filter-bar />`). Its assets are injected once, in dependency order: styles before `</head>`, scripts before `</body>`. To choose where, put `@manifestStyles` in `<head>` and `@manifestScripts` before `</body>`. To load an asset without a component: `@manifestRequire('filters::date-range')` or `Manifest::require('filters::date-range')`.
- **JS:** import package code by alias: `import { FilterBar } from "@javaabu/filters"`. Run `php artisan manifest:list` to see packages, assets and entries.
- **Inertia:** `Inertia::render('filters::Index')`, with the app resolving pages through the helper:

```tsx
import { resolvePage } from "@javaabu/manifest/inertia"

createInertiaApp({
  resolve: (name) => resolvePage(name, import.meta.glob("./pages/**/*.tsx")),
  // setup...
})
```

## Overriding a package file

```bash
php artisan manifest:override filters js/components/FilterBar.tsx
```

Edit `resources/vendor/filters/js/components/FilterBar.tsx`. It's used everywhere the package uses that file: entries, alias imports, the package's own relative imports, and Inertia pages. Its own relative imports still resolve to the package. Keep the same file name and extension. Restart `npm run dev` after adding an override.

## Rules

- Never hand-write `<script>`/`<link>` tags for package assets, and never add package entries to `laravel({ input })`. The plugin adds them, and the tags come from Laravel's Vite (dev server, build, CSP nonce).
- Never edit `vendor/` or copy package code into `resources/js` to change it. Override the single file.
- Asset ids are `package::name`; a bare package name means all of its assets.
- `@manifestStyles` / `@manifestScripts` are optional. Don't add them "to be safe" in partials; once per layout is enough.
- Packages' npm imports come from the app's `node_modules`. When the build says it can't resolve `tom-select` from a vendor file, run `php artisan manifest:check` and install what it lists.
- In tests: `Manifest::fake()`, then `Manifest::assertAssetRequired('filters::filter-bar')` / `assertAssetInjected()`. No Vite build is needed.

## Things that differ from what you might assume

- Package entries are Vite inputs named by their path from the app root (`vendor/javaabu/filters/resources/js/filter-bar.ts`), so they appear in `public/build/manifest.json` like the app's own.
- Injection only touches HTML responses with `</head>`/`</body>`. JSON, downloads, redirects, Inertia requests and fragments are left alone.
- `@manifestStyles` in `<head>` still gets components rendered later in the body. Directives are filled at the end of the request.
- An app without Vite still works for packages that ship `dist`: files are served from `/vendor/manifest/{package}/`. Publish them in production with `php artisan vendor:publish --tag=manifest-dist --force`.
- `@javaabu/manifest/inertia` needs no npm install: the Vite plugin resolves it.
