---
title: Overriding assets
sidebar_position: 5
---

An app changes a package's frontend one file at a time. A file in `resources/vendor/{package}/`, at the same path the package has it under its `resources/` folder, is used instead of the package's:

| Package file | Override |
| --- | --- |
| `vendor/javaabu/filters/resources/js/components/FilterBar.tsx` | `resources/vendor/filters/js/components/FilterBar.tsx` |
| `vendor/javaabu/filters/resources/js/Pages/Index.tsx` | `resources/vendor/filters/js/Pages/Index.tsx` |
| `vendor/javaabu/filters/resources/css/filters.css` | `resources/vendor/filters/css/filters.css` |

`resources/vendor` can be changed with `manifest.overrides_path`.

# Overriding one file

```bash
php artisan manifest:override filters js/components/FilterBar.tsx
php artisan manifest:override filters::filter-bar     # an asset's entry
```

This copies the package's file to the override path, ready to edit. It won't overwrite an override you already have unless you pass `--force`.

Restart `npm run dev` after adding or removing an override, so Vite picks up the change. Then run `npm run build` as usual.

# Publishing everything

```bash
php artisan vendor:publish --tag=manifest-assets            # every package
php artisan vendor:publish --tag=manifest-assets-filters    # one package
```

This copies the package's asset folders (its JS folder, its pages, and the folders of its entries) into `resources/vendor/{package}/`. Every published file is then an override. You don't have to publish to override: copying only the files you change keeps the rest up to date with the package.

# Resolution order

For any file a package uses, the first one that exists wins:

1. `resources/vendor/{package}/{path}`, the app's override
2. `{package}/resources/{path}`, the package's own file

That applies to:

- **Entries.** The tags Laravel prints, and the Vite build's inputs, point at the override.
- **Imports through the package's alias.** `import { FilterBar } from '@javaabu/filters/components/FilterBar'` gets the override.
- **The package's relative imports.** When the package's `index.ts` does `import { FilterBar } from './components/FilterBar'`, it gets the override too. One overridden component is used everywhere in the package.
- **The override's own relative imports.** In `resources/vendor/filters/js/components/FilterBar.tsx`, `import { label } from './label'` resolves to the package's `js/components/label.ts`, unless that's overridden too. You copy only what you change.
- **Inertia pages.** `filters::Index` renders the override page.
- **Page types.** `php artisan manifest:types` points the page's type at the override.

The override must have the same file name, including the extension. To replace `FilterBar.tsx`, write `FilterBar.tsx`, not `FilterBar.jsx`. A page is the exception: a `Pages/Index.vue` override of `Pages/Index.tsx` works, because pages are found by name.

Overridden assets are always built by the app's Vite, never served from the package's [prebuilt files](../advanced-usage/prebuilt-assets).

# Seeing what's overridden

`php artisan manifest:list` marks overridden entries:

```
| filters::filter-bar | ts | resources/vendor/filters/js/filter-bar.ts (override) | widgets::core |
```

# Blade views

Overriding a Blade component's view works the usual Laravel way, in `resources/views/vendor/{package}`. Manifest only handles the frontend assets.
