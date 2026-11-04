# Manifest

[![Tests](https://github.com/Javaabu/manifest/actions/workflows/tests.yml/badge.svg)](https://github.com/Javaabu/manifest/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/Javaabu/manifest/branch/main/graph/badge.svg)](https://codecov.io/gh/Javaabu/manifest)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/javaabu/manifest.svg)](https://packagist.org/packages/javaabu/manifest)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)

Ship CSS, Sass, JS, TypeScript, React and Vue from Laravel packages. A package registers its assets in its service provider. Its Blade components load them on the pages they render on, the app's own Vite builds them, Inertia renders the package's pages, and apps override any single file.

```php
// In a package's service provider
$this->registerAssets('filters', __DIR__ . '/../resources', function (Package $package) {
    $package
        ->style('filters', 'css/filters.css')
        ->script('filter-bar', 'js/filter-bar.ts', depends_on: ['filters'])
        ->js()      // import { FilterBar } from '@javaabu/filters'
        ->pages();  // Inertia::render('filters::Index')
});
```

```php
#[RequiresAssets('filters::filter-bar')]
class FilterBar extends Component {}
```

## Documentation

You'll find the documentation on [https://docs.javaabu.com/manifest](https://docs.javaabu.com/manifest), and in the [`docs`](docs) folder.

AI coding assistants can start from [`llms.txt`](llms.txt). The package ships a [Laravel Boost](https://github.com/laravel/boost) skill and guideline, which Boost finds by itself. See [AI assistants](docs/ai-assistants.md).

## Quick start

```bash
composer require javaabu/manifest
```

```js
// vite.config.js
import laravel from "laravel-vite-plugin"
import { defineConfig } from "vite"
import manifest from "./vendor/javaabu/manifest/dist/vite.js"

export default defineConfig({
  plugins: [laravel({ input: ["resources/js/app.js"], refresh: true }), manifest()],
})
```

```bash
php artisan manifest:check   # installs what packages need from npm
php artisan manifest:types   # TypeScript paths and Inertia page types
```

| Building | Read |
| --- | --- |
| An app | [Installation & Setup](docs/installation-and-setup.md), [Loading assets in Blade](docs/basic-usage/loading-assets.md), [Inertia pages](docs/basic-usage/inertia-pages.md) |
| A package | [Registering assets](docs/basic-usage/registering-assets.md), [Building a package](docs/advanced-usage/building-a-package.md) |

The reference packages are in [`tests/Fixtures/app/vendor/acme`](tests/Fixtures/app).

## Testing

```bash
composer test          # PHPUnit
composer lint          # Pint
pnpm install
pnpm lint && pnpm format:check && pnpm typecheck
pnpm build             # dist/, committed
pnpm test:coverage     # Vitest: unit tests, plus real Vite builds and a dev server (needs PHP)
vendor/bin/phpunit --group e2e   # renders pages against that build
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email [info@javaabu.com](mailto:info@javaabu.com) instead of using the issue tracker.

## License

MIT. See [LICENSE.md](LICENSE.md).
