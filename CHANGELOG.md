# Changelog

All notable changes to `javaabu/manifest` are documented in this file.

## Unreleased

Initial development of v1.0:

- Asset registration for package service providers: CSS, Sass, JS, TS, React and Vue entries, dependencies across packages, npm dependencies, import aliases, Inertia pages and prebuilt dist.
- Automatic injection of required assets into HTML responses, `#[RequiresAssets]`, `DeclaresAssets` and `@manifestRequire`, and the `@manifestStyles` / `@manifestScripts` directives.
- A Vite plugin that discovers packages, builds their assets, resolves `@javaabu/<pkg>` imports and applies single-file overrides.
- `resolvePage()` for Inertia React and Vue apps, and generated TypeScript types for package pages.
- Prebuilt assets for apps without Vite, served from `/vendor/manifest/{package}` and publishable.
- `manifest:list`, `manifest:check`, `manifest:discover`, `manifest:types` and `manifest:override`, in English and Dhivehi.
- `packageBuild()`, a Vite preset for building a package's dist.
- `Manifest::fake()` with assertions for tests.
- `assertInertia()` finds package pages on inertia-laravel v3 as well as v2 (#15).
