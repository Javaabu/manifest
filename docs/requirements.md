---
title: Requirements
sidebar_position: 1.1
---

This package requires:

- PHP 8.4 or higher
- Laravel 12 or 13

To build assets with Vite (recommended):

- Node.js 20.19 or higher
- Vite 6, 7 or 8, with [laravel-vite-plugin](https://github.com/laravel/vite-plugin)
- Whatever the packages' assets need, such as `@vitejs/plugin-react`, `@vitejs/plugin-vue` or `sass`. Run `php artisan manifest:check` to see exactly what's missing.

For Inertia pages: [inertia-laravel](https://github.com/inertiajs/inertia-laravel) with `@inertiajs/react` or `@inertiajs/vue3`.

Apps that don't use Vite can still use packages that ship prebuilt assets. See [Prebuilt assets](./advanced-usage/prebuilt-assets).
