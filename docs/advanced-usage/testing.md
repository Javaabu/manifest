---
title: Testing
sidebar_position: 4
---

# Faking the manifest

In a package's tests, or an app's, `Manifest::fake()` records what's required and what's injected, and prints simple tags instead of asking Vite. No build or dev server is needed:

```php
<?php

namespace Javaabu\Filters\Tests\Feature;

use Javaabu\Filters\Tests\TestCase;
use Javaabu\Manifest\Facades\Manifest;
use PHPUnit\Framework\Attributes\Test;

class FilterBarTest extends TestCase
{
    #[Test]
    public function it_loads_the_filter_bar_scripts_on_the_page(): void
    {
        Manifest::fake();

        $this->get('/reports')
            ->assertOk()
            ->assertSee('data-manifest-asset="filters::filter-bar"', false);

        Manifest::assertAssetRequired('filters::filter-bar');
        Manifest::assertAssetInjected('filters::filter-bar');
        Manifest::assertAssetNotRequired('filters::date-range');
    }
}
```

The fake keeps every registered package. It records across requests and outside them, such as `Blade::render()` in a test.

| Method | Asserts |
| --- | --- |
| `assertAssetRequired($id)` | the asset (or a dependency of one) was required |
| `assertAssetNotRequired($id)` | it wasn't |
| `assertNothingRequired()` | nothing was |
| `assertAssetInjected($id)` | its tag was printed into a page, by a directive or injection |
| `assertAssetNotInjected($id)` | it wasn't |

`Manifest::fake()` returns the fake: `requiredAssets()` and `injectedAssets()` list the ids, in order.

The fake's tags look like this, with the entry path relative to the app:

```html
<link rel="stylesheet" href="/vendor/javaabu/filters/resources/css/filters.css" data-manifest-asset="filters::filters" />
<script type="module" src="/vendor/javaabu/filters/resources/js/filter-bar.ts" data-manifest-asset="filters::filter-bar"></script>
```

# Without the fake

Without the fake, tags come from Laravel's `Vite`. In tests you can use Laravel's `$this->withoutVite()`, which prints no tags, or write a `public/build/manifest.json` for the test.

# View assertions

Injection keeps the response's view, so `assertViewIs()`, `assertViewHas()` and `viewData()` work on pages that had assets injected:

```php
$this->get('/reports')
    ->assertViewIs('filters::reports.index')
    ->assertViewHas('title', 'ރިޕޯޓުތައް');
```

# Inertia pages

`assertInertia()->component('filters::Index')` finds package pages. See [Inertia pages](../basic-usage/inertia-pages#testing).

# Registration

Check registration in a package's test suite with `manifest:list`:

```php
$this->artisan('manifest:list', ['package' => 'filters'])->assertSuccessful();

$this->assertTrue(Manifest::has('filters::filter-bar'));
```
