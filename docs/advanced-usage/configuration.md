---
title: Configuration
sidebar_position: 5
---

Every option has a default, and installing the package needs no configuration. Publish the file to change one:

```bash
php artisan vendor:publish --tag="manifest-config"
```

| Option | Default | What it does |
| --- | --- | --- |
| `inject` | `true` | Inject required styles before `</head>` and scripts before `</body>` in HTML responses. With `false`, only the [directives](../basic-usage/loading-assets#placing-assets-yourself) print assets. Directives are filled either way. |
| `overrides_path` | `null` (`resources/vendor`) | Where apps [override](../basic-usage/overriding-assets) package files: `{overrides_path}/{package}/{path}`. An absolute path. |
| `discovery_path` | `null` (`bootstrap/cache/manifest.json`) | Where `manifest:discover` writes the file the Vite plugin reads. An absolute path. Change the plugin's `discovery` option to match. |
| `dist` | `'auto'` | When to use packages' [prebuilt assets](./prebuilt-assets): `'auto'` (when the app's build doesn't have the asset), `'always'` or `'never'`. |
| `dist_url` | `'vendor/manifest'` | The URL path prebuilt files are served from, and published to under `public/`. |

Settings that belong to Vite stay with Vite: the build directory, hot file, CSP nonce, integrity key and asset URLs are read from Laravel's `Vite` (`Vite::useBuildDirectory()`, `Vite::useCspNonce()`, …) and apply to package assets too.

# Vite plugin options

See [Vite](../basic-usage/vite#options): `discover`, `discovery` and `input`.
