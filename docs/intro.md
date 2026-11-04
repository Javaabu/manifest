---
title: Introduction
sidebar_position: 1.0
---

# Manifest

[Manifest](https://github.com/Javaabu/manifest) lets Laravel packages ship CSS, Sass, JavaScript, TypeScript, React and Vue to the apps that install them. A package registers its assets in its service provider, the way it registers views or config:

```php
$this->registerAssets('filters', __DIR__ . '/../resources', function (Package $package) {
    $package
        ->style('filters', 'css/filters.css')
        ->script('filter-bar', 'js/filter-bar.ts', depends_on: ['filters'])
        ->js()
        ->pages();
});
```

The app doesn't have to do anything else:

- **Blade components load their own assets.** A component marked `#[RequiresAssets('filters::filter-bar')]` gets its CSS and JS on every page it renders on: injected once, in dependency order, styles in `<head>` and scripts before `</body>`. Or place them yourself with `@manifestStyles` and `@manifestScripts`.
- **The app's Vite builds them.** One line in `vite.config.js` adds every package's entries to the build, with hot reloading in development, and makes each package importable: `import { FilterBar } from '@javaabu/filters'`.
- **Inertia renders package pages.** `Inertia::render('filters::Index')` works with React and Vue, with TypeScript types for every page.
- **Apps override any single file.** Put your copy of a component in `resources/vendor/filters/js/FilterBar.tsx`, and it's used everywhere the package uses it.
- **Apps without Vite still work**, from the package's prebuilt files.

Tags are printed by Laravel's own `Vite` class, so the dev server, the build manifest, `Vite::useCspNonce()` and integrity hashes behave exactly as they do for `@vite`.

| You are | Start with |
| --- | --- |
| Building an app | [Installation & Setup](./installation-and-setup) |
| Building a package that ships assets | [Registering assets](./basic-usage/registering-assets), then [Building a package](./advanced-usage/building-a-package) |

Using an AI coding assistant? See [AI assistants](./ai-assistants).
