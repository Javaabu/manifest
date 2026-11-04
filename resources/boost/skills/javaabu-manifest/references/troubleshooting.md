# Troubleshooting package assets

| Symptom | Cause and fix |
| --- | --- |
| `Asset [x::y] is not registered.` | The package isn't installed, or registers the asset under another name. `php artisan manifest:list` shows what's registered. |
| `Circular asset dependency: a -> b -> a.` | Two assets depend on each other. Remove one `depends_on`. |
| `Unable to locate file in Vite manifest: vendor/...` | The app's build predates the package (or an override). Run `npm run build`. Check that `manifest()` is in `vite.config.js`. |
| `Vite manifest not found` | No `npm run build` yet, and the package has no `dist`. Build, or run `npm run dev`. |
| Build: `Failed to resolve import "tom-select" from "vendor/..."` | The app is missing an npm package the Laravel package needs. Run `php artisan manifest:check` and install what it lists. |
| `bootstrap/cache/manifest.json doesn't exist` from the plugin | PHP couldn't run where Vite runs. Use `manifest({ discover: "./vendor/bin/sail artisan manifest:discover" })`, or run `php artisan manifest:discover` first and set `discover: false`. |
| Tags missing on a page | The response isn't HTML with `</head>`/`</body>` (a fragment, JSON, Inertia request), or `manifest.inject` is `false` with no directives in the layout. Check that the component really requires the asset with `Manifest::fake()` in a test. |
| Assets printed twice | Something prints them by hand (`@vite('vendor/...')`, a `<script>` tag). Remove it; manifest prints each asset once. |
| An override isn't used | Wrong path (it mirrors the package's `resources/` folder: `resources/vendor/{package}/js/...`), different extension, or `npm run dev` wasn't restarted. `manifest:list` marks overridden entries. |
| `Page not found: x::Page` | The package doesn't register `->pages()`, or the page isn't in that folder. Page names are case-sensitive paths without the extension. |
| TypeScript can't find `@javaabu/x` | Run `php artisan manifest:types`. If `tsconfig.json` has comments, paste the printed paths yourself. |
| Prebuilt files 404 in production | `manifest.dist_url` changed, or a web server rule blocks `/vendor`. Publish them: `php artisan vendor:publish --tag=manifest-dist --force`. |
| A component's assets don't load after installing manifest | Compiled Blade views predate it. Run `php artisan view:clear`. |
