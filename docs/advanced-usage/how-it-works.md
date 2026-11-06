---
title: How it works
sidebar_position: 8
---

For package authors and anyone debugging.

# Registration

`registerAssets()` uses `callAfterResolving(Manifest::class)`, so a package's callback runs when the `Manifest` singleton is first made, whichever order providers load in. `Package` validates names and entry types as you call it.

# Requirements

What a request needs lives in `AssetRequirements`, a **scoped** binding, so it resets between requests under Octane and queue workers. Requiring an asset resolves it with its dependencies straight away (a depth-first walk, which detects cycles), so mistakes throw on the line that caused them.

Blade class components are hooked in two places: a container `afterResolving` callback, and a Blade precompiler that adds `requireFor($component)` right after the component is made. The second is needed because Blade only uses the container when some constructor arguments are missing. Run `php artisan view:clear` after installing manifest if you have compiled views.

# Rendering

`@manifestStyles` and `@manifestScripts` print placeholders (`<!--[manifest:styles:*]-->`). The `InjectAssets` middleware is pushed onto the global middleware stack. After the response is built, it fills the placeholders: scoped ones first, in document order, then catch-alls. With `inject` on, it puts what's left before `</head>` and `</body>`. It keeps the response's original view, so Laravel's view assertions still work. Because it's middleware, rendering errors (an unknown asset, a missing Vite manifest) go through the exception handler like any other error.

Each batch of tags is printed by a clone of the app's `Illuminate\Foundation\Vite`, with each asset's entry path relative to the app root as the entrypoint. That's what Vite's build manifest is keyed by. Prebuilt assets use `DistVite`, a subclass that reads the package's own manifest and copies the app's Vite settings.

# The Vite plugin

`manifest:discover` writes `bootstrap/cache/manifest.json`:

```json
{
  "version": 1,
  "packages": {
    "filters": {
      "path": "vendor/javaabu/filters/resources",
      "alias": "@javaabu/filters",
      "js": "js",
      "pages": "js/Pages",
      "dist": "../dist",
      "overrides": "resources/vendor/filters",
      "dependencies": { "@javaabu/onu": "^1.0" },
      "peerDependencies": { "react": "^19.0" },
      "vitePlugins": [{ "module": "../dist/vite.js", "options": { "watch": ["lang"] } }]
    }
  },
  "assets": [
    {
      "id": "filters::filter-bar",
      "package": "filters",
      "name": "filter-bar",
      "type": "ts",
      "entry": "js/filter-bar.ts",
      "input": "vendor/javaabu/filters/resources/js/filter-bar.ts",
      "dependencies": ["filters::filters"]
    }
  ]
}
```

`manifest()` returns its plugin and a promise-like of the packages' plugins. Vite awaits it while it flattens the config's plugins, which runs discovery (from the current directory), imports each package's `vitePlugins` module and calls its default export with the options, the app's `plugins` settings merged in.

The plugin (`enforce: "pre"`):

- `config`: runs discovery (unless the package plugins already have, from the same root), and adds package and override folders to `server.fs.allow`.
- `configResolved`: warns about failed discovery, aliases that shadow npm packages, `plugins` settings for unknown packages, and package plugins also added in `vite.config.js`.
- `options`: in a client build, adds every asset's entry to Rollup's `input`, with overrides checked at build time (the same rule PHP applies at request time).
- `resolveId`:
  - `@javaabu/{pkg}[/path]` maps to the package's JS folder, then overrides apply.
  - Relative imports from package files are overridden.
  - Relative imports from override files resolve as if from the package's copy.
  - Bare imports from package files resolve from the app root.
- `load`: the `virtual:javaabu-manifest/pages` module, with lazy `import.meta.glob()`s over each package's pages folder and its overrides.
- `writeBundle`: rewrites manifest keys of symlinked packages (`../filters/resources/...`) to `vendor/...`.

# Paths

Everything PHP writes or compares is relative to the app root. PHP's `__DIR__` is the real path of a symlinked package, so `relativeToBase()` maps it back to `vendor/{name}` using `vendor/composer/installed.json`.
