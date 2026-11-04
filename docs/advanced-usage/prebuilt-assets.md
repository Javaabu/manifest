---
title: Prebuilt assets
sidebar_position: 2
---

A package can ship its assets already built, for apps that don't build with Vite, or whose build doesn't include the package yet. Build them with the [`packageBuild()` preset](./building-a-package#5-prebuilt-assets-optional) and register the folder:

```php
$package->dist();            // resources/../dist
$package->dist('../build');  // relative to the resources folder
```

The folder must have a Vite manifest, at `.vite/manifest.json` (where Vite 5+ writes it) or `manifest.json`. Its keys are the registered entries (`js/filter-bar.ts`), as the preset writes them. Keys relative to the package root (`resources/js/filter-bar.ts`) work too.

# When they're used

`manifest.dist` decides, per asset:

| Value | Uses the package's prebuilt files |
| --- | --- |
| `auto` (default) | when the app's Vite isn't running hot and its build (`public/build/manifest.json`) doesn't have the asset |
| `always` | whenever the package has the asset prebuilt |
| `never` | never; a missing asset is Vite's usual error |

An asset the app [overrides](../basic-usage/overriding-assets) always comes from the app's Vite.

So with `auto`, an app that adds the Vite plugin builds everything itself. An app without it, or one that installed a package since its last build, still gets working pages.

Tags for prebuilt files go through the same Vite code as the app's: CSP nonce, integrity, preloading, tag attributes and `Vite::createAssetPathsUsing()` all apply. File names are hashed, so they're safe to cache forever.

# Where they're served from

Prebuilt files have one URL, whether or not they're published:

```
/vendor/manifest/{package}/assets/filter-bar-4kD8s1.js
```

- **Published**, with `php artisan vendor:publish --tag=manifest-dist` (or `--tag=manifest-dist-{package}`), they're copied to `public/vendor/manifest/{package}`, and the web server serves them directly. Publish them in production, as part of your deploy.
- **Not published**, a route at the same URL serves them from the package, with `Cache-Control: public, max-age=31536000, immutable`. It only serves files inside the package's dist folder.

Change the URL with `manifest.dist_url`:

```php
'dist_url' => 'assets/packages',   // /assets/packages/{package}/...
```

With route caching, the route is part of the cache like any other package route.

Republish after updating a package, since the file names change:

```json
"scripts": {
    "post-update-cmd": [
        "@php artisan vendor:publish --tag=laravel-assets --ansi --force",
        "@php artisan vendor:publish --tag=manifest-dist --ansi --force"
    ]
}
```

# Bundling

Apps without Vite load the files as they are, so the preset bundles every import. Only leave a module out (`external`) if the page provides it another way, such as an import map:

```js
packageBuild({ input: ["js/editor.ts"], external: ["vue"] })
```
