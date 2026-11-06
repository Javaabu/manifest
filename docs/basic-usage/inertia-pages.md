---
title: Inertia pages
sidebar_position: 4
---

Packages ship Inertia pages, and apps render them by name:

```php
use Inertia\Inertia;

return Inertia::render('filters::Reports/Show', [
    'report' => $report,
]);
```

`filters::Reports/Show` is `resources/js/Pages/Reports/Show.tsx` (or `.jsx`, `.vue`, `.ts`, `.js`, `.svelte`) in the `filters` package, or the app's override of it.

# In the package

Register the pages folder:

```php
$this->registerAssets('filters', __DIR__ . '/../resources', function (Package $package) {
    $package
        ->js()
        ->pages()   // resources/js/Pages
        ->npm(['@javaabu/forms' => 'github:Javaabu/react-forms#main'], peer_dependencies: [
            'react' => '^19.0',
            '@inertiajs/react' => '^2.0',
        ], for: 'pages');   // Blade apps don't need these
});
```

Each page is a component with a default export, as in an app:

```tsx
// resources/js/Pages/Reports/Show.tsx
import { Head } from "@inertiajs/react"
import { FilterBar } from "../../components/FilterBar"

export default function Show({ report }: { report: { id: number; title: string } }) {
  return (
    <>
      <Head title={report.title} />
      <FilterBar />
      <h1>{report.title}</h1>
    </>
  )
}
```

Import the package's own code relatively, or through its alias (`@javaabu/filters/...`). Bare imports (`react`, `@inertiajs/react`, `@javaabu/forms`) resolve from the app's `node_modules`, so declare them with `->npm()`.

# In the app

## React

```tsx
// resources/js/app.tsx
import { createInertiaApp } from "@inertiajs/react"
import { resolvePage } from "@javaabu/manifest/inertia"
import { createRoot } from "react-dom/client"

createInertiaApp({
  resolve: (name) => resolvePage(name, import.meta.glob("./pages/**/*.tsx")),
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />)
  },
})
```

## Vue

```ts
// resources/js/app.ts
import { createInertiaApp } from "@inertiajs/vue3"
import { resolvePage } from "@javaabu/manifest/inertia"
import { createApp, h, type DefineComponent } from "vue"

createInertiaApp({
  resolve: (name) => resolvePage<DefineComponent>(name, import.meta.glob<DefineComponent>("./pages/**/*.vue")),
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el)
  },
})
```

## Server rendering

Use the same `resolve` in `resources/js/ssr.tsx`. The plugin leaves SSR builds' inputs alone, and resolves `@javaabu/manifest/inertia` there too.

# How names resolve

`resolvePage(name, pages, options?)`:

- `package::Page` looks for the app's override in `resources/vendor/{package}/js/Pages/`, then the package's pages. A missing page or package throws `Page not found: …` and says where it looked.
- Any other name is one of the app's pages, looked up in `pages`, the `import.meta.glob()` result (lazy or eager). Without `options.base`, the shortest key ending in `/{name}` wins, so `Dashboard` is `./pages/Dashboard.tsx` and not `./pages/admin/Dashboard.tsx`. Pass `{ base: "./pages/" }` to be exact.
- `pages` can also be a function, so you can keep a resolver you already have:

```ts
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers"

resolve: (name) =>
  resolvePage(name, (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob("./pages/**/*.tsx"))),
```

Package pages are code-split like the app's: each is its own chunk, loaded when it's visited.

Other helpers from `@javaabu/manifest/inertia`:

| Export | What |
| --- | --- |
| `resolvePackagePage(package, page, options?)` | Resolves `package::page` only |
| `isPackagePage(name)` | Whether a name has `::` |
| `parsePageName(name)` | `{ package, page }`, or `null` |
| `packagePageNames()` | Every package page, like `["filters::Index", "filters::Reports/Show"]` |
| `packagePage(name)` | Returns the name, typed: a compile error for a page no package ships (with [generated types](../advanced-usage/typescript)) |

# Overriding a page

Copy the page into the app and change it:

```bash
php artisan manifest:override filters js/Pages/Reports/Show.tsx
```

It's at `resources/vendor/filters/js/Pages/Reports/Show.tsx`, and it wins. Its relative imports (`../../components/FilterBar`) still resolve to the package's files, unless you've overridden those too. See [Overriding assets](./overriding-assets).

# Testing

inertia-laravel checks that a page exists in `assertInertia()`, and on v3 also when rendering if `inertia.pages.ensure_pages_exist` is on. The package registers its pages with that check on inertia-laravel v2 and v3, so package pages pass it with no setup in your tests:

```php
$this->get('/reports/1')
    ->assertInertia(fn (AssertableInertia $page) => $page
        ->component('filters::Reports/Show')
        ->where('report.title', 'ރިޕޯޓް'));
```

The check uses the page extensions from inertia-laravel's config (`inertia.pages.extensions` on v3). An app's override of a package page is found first.
