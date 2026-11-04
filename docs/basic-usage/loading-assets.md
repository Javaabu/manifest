---
title: Loading assets in Blade
sidebar_position: 2
---

An asset loads on a page when something on the page **requires** it. Required assets are printed once each, in dependency order, wherever the page asks for them. If the page doesn't ask, they're injected for it.

# Requiring assets

## From a Blade component

Mark the component class with `#[RequiresAssets]`:

```php
<?php

namespace Javaabu\Filters\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use Javaabu\Manifest\Attributes\RequiresAssets;

#[RequiresAssets('filters::filter-bar')]
class FilterBar extends Component
{
    public function render(): View
    {
        return view('filters::components.filter-bar');
    }
}
```

Whenever `<x-filters::filter-bar />` renders, `filters::filter-bar` and its dependencies load. The attribute takes several assets, and can be repeated. Subclasses inherit it, so a component extending `FilterBar` needs the same assets plus its own.

When the assets depend on the component's props, implement `DeclaresAssets`:

```php
use Javaabu\Manifest\Contracts\DeclaresAssets;

class DatePicker extends Component implements DeclaresAssets
{
    public function __construct(
        public bool $inline = false,
    ) {}

    public function requiredAssets(): array
    {
        return $this->inline
            ? ['forms::date-picker-inline']
            : ['forms::date-picker'];
    }
}
```

Both work together: the attribute's assets, then `requiredAssets()`.

## From an anonymous component or a view

```blade
{{-- resources/views/components/chart.blade.php --}}
@props(['label'])
@manifestRequire('widgets::chart')

<div data-widget-chart data-label="{{ $label }}"></div>
```

`@manifestRequire` takes one or more assets, or an array.

## From PHP

```php
use Javaabu\Manifest\Facades\Manifest;

Manifest::require('filters::filter-bar');
Manifest::require('filters::filter-bar', 'widgets::chart');
Manifest::require(['filters::filter-bar']);
Manifest::require('filters');          // every asset of the package
```

Call it anywhere during the request: a controller, a view composer, middleware.

An unknown asset, or a broken dependency, throws when it's required, so the error shows up on the view or line that required it.

# Automatic injection

With nothing else in the layout, the required assets are injected into the response:

- styles just before `</head>`,
- scripts just before `</body>`,
- each asset once, after the assets it depends on.

```html
<!DOCTYPE html>
<html lang="dv" dir="rtl">
<head>
    <title>ފިލްޓަރުތައް</title>
<link rel="stylesheet" href="/build/assets/theme-B8h2kQ.css" /><link rel="stylesheet" href="/build/assets/filters-Ck1x9a.css" /></head>
<body>
    <div class="filter-bar" data-filter-bar>ފިލްޓަރު</div>
<script type="module" src="/build/assets/core-D3jd8s.js"></script><script type="module" src="/build/assets/filter-bar-a8Kd2j.js"></script></body>
</html>
```

Only HTML responses are touched. JSON responses, downloads, streamed and file responses, redirects and Inertia requests (`X-Inertia`) are left alone, and so are pages with nothing required. A fragment without `</head>` or `</body>`, such as an HTMX partial, gets nothing injected, because there's nowhere to put the tags. Place them yourself with the directives if a fragment needs them.

Injection runs as global middleware (`Javaabu\Manifest\Http\Middleware\InjectAssets`), which the package adds itself. To use only the directives, turn it off:

```php
// config/manifest.php
'inject' => false,
```

# Placing assets yourself

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'dv' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @manifestStyles
</head>
<body>
    {{ $slot }}

    @manifestScripts
</body>
</html>
```

| Directive | Prints |
| --- | --- |
| `@manifestStyles` | every required style not printed yet |
| `@manifestScripts` | every required script not printed yet |
| `@manifestStyles('filters')`, `@manifestScripts('filters')` | the required assets of the `filters` package, and what they depend on |
| `@manifestScripts('filters::filter-bar')` | requires `filters::filter-bar` and prints it, with what it depends on |
| `@manifestScripts('filters', 'widgets::chart')` | several at once |

The directives are filled in at the end of the request, so `@manifestStyles` in `<head>` also gets the styles of components rendered further down the page. Directives for a package or an asset are filled first, and the catch-all `@manifestStyles` / `@manifestScripts` gets the rest. Whatever a directive prints isn't injected again. Auto-injection only adds what no directive printed.

## Outside a request

The directives need the response to be filled in. For a view rendered on its own, such as a mailable or a PDF, render the tags directly, after the content that requires them:

```php
$body = view('reports.show', ['report' => $report])->render();

$styles = Manifest::renderStyles();   // HtmlString
$scripts = Manifest::renderScripts(['filters']);
```

Each asset is still printed only once.

# Vite, CSP and cache busting

Tags are printed by Laravel's own `Illuminate\Foundation\Vite`, the class behind `@vite`:

- In development (`npm run dev`), they point at the Vite dev server, with hot reloading.
- After `npm run build`, they point at the hashed files in `public/build`, so URLs change when the files do. Imports are preloaded with `<link rel="modulepreload">`.
- `Vite::useCspNonce()` adds the nonce to every tag. `Vite::useIntegrityKey()`, `Vite::useScriptTagAttributes()`, `Vite::useStyleTagAttributes()` and `Vite::createAssetPathsUsing()` apply too.

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Vite;

public function boot(): void
{
    Vite::useCspNonce();
}
```

A missing build (no `public/build/manifest.json`, or an asset that isn't in it) throws Vite's usual error, unless the package ships [prebuilt assets](../advanced-usage/prebuilt-assets).
