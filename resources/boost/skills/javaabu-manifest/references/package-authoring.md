# Writing a package that ships assets

Full guide: `vendor/javaabu/manifest/docs/advanced-usage/building-a-package.md`. Reference packages: `tests/Fixtures/app/vendor/acme/{filters,widgets}` in the javaabu/manifest repository.

## Register in the service provider

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
                ->react('date-range', 'js/date-range.tsx')
                ->js()          // resources/js importable as @javaabu/filters
                ->pages()       // resources/js/Pages/*: Inertia::render('filters::Index')
                ->dist()        // optional prebuilt ../dist for apps without Vite
                ->npm(['tom-select' => '^2.4'], peer_dependencies: ['react' => '^19.0', 'react-dom' => '^19.0']);
        });
    }
}
```

- Require `javaabu/manifest` in the package's `composer.json`.
- Entries are relative to the folder passed to `registerAssets()`. The type comes from the extension: `style()` takes `.css/.scss/.sass`, `script()` takes `.js/.ts/.jsx/.tsx/.vue`. Use `react()`/`vue()` for scripts that need those frameworks, so `manifest:check` knows.
- Names are unique per package (a style and a script can't share one). Bare `depends_on` names mean the same package.
- If `@javaabu/{name}` is also an npm package name (javaabu/forms vs the React `@javaabu/forms`), use `->alias('@javaabu/forms-blade')`.

## Declare what components need

```php
use Javaabu\Manifest\Attributes\RequiresAssets;

#[RequiresAssets('filters::filter-bar')]
class FilterBar extends Component {}
```

```php
use Javaabu\Manifest\Contracts\DeclaresAssets;

class DatePicker extends Component implements DeclaresAssets
{
    public function requiredAssets(): array
    {
        return $this->inline ? ['forms::date-picker-inline'] : ['forms::date-picker'];
    }
}
```

Anonymous components: `@manifestRequire('filters::chart')` at the top of the view.

## Write the JS

- Import your own code relatively, other packages through their alias (`@javaabu/widgets`), npm packages by name. Every npm import must be in `->npm()`.
- A script's CSS can be imported by the script (`import './select.css'`). Register standalone CSS that must be in `<head>` as a style.
- Inertia pages live in `resources/js/Pages/**` with a default export. `Pages/Reports/Show.tsx` is `filters::Reports/Show`.
- Use logical CSS (`margin-inline-start`, `ps-4`), never left/right, and `@javaabu/i18n` for strings.

## Prebuilt dist (optional)

```js
// vite.config.js in the package
import { defineConfig } from "vite"
import { packageBuild } from "./vendor/javaabu/manifest/dist/package.js"

export default defineConfig(packageBuild({ input: ["css/filters.css", "js/filter-bar.ts"] }))
```

`npx vite build` writes `dist/` with `.vite/manifest.json` keyed by the entries. Register it with `->dist()`.

## Test

```php
use Javaabu\Manifest\Facades\Manifest;

Manifest::fake();

$this->get('/reports')->assertSee('data-manifest-asset="filters::filter-bar"', false);

Manifest::assertAssetRequired('filters::filter-bar');
```

`assertInertia()->component('filters::Index')` works for package pages.

## Common mistakes

- Calling `Manifest::package()` from `register()`, before javaabu/manifest's own provider may have registered. Use the `RegistersAssets` trait, which works from `register()` or `boot()`.
- Publishing JS to the app with `vendor:publish` so apps can build it. Don't: the Vite plugin builds it from vendor/. Apps override single files instead.
- Forgetting `->npm()` for an import. The app's build fails with "failed to resolve import" from a vendor/ file.
- Hard-coding `/vendor/...` URLs or `Vite::asset()` calls for your assets. Require them by id.
