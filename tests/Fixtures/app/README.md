# Fixture app and reference packages

A small Laravel app that the PHPUnit and end-to-end tests run in. The two packages in `vendor/acme` are installed the way Composer would install them, and they're the reference for next-gen packages that ship assets through javaabu/manifest:

| Package | Shows |
| --- | --- |
| [`vendor/acme/filters`](vendor/acme/filters) | Registration from `boot()`, a Blade component with `#[RequiresAssets]`, one with `DeclaresAssets`, a React entry, TypeScript imports across packages (`@javaabu/widgets`), and Inertia pages (`filters::Index`) |
| [`vendor/acme/widgets`](vendor/acme/widgets) | Registration from `register()`, Sass, plain JS, a Vue entry, an anonymous component with `@manifestRequire`, and a prebuilt `dist` built with the `packageBuild()` preset (`vite.config.js`) |

The app's `vite.config.js` is the one an app writes, apart from discovery running through `vendor/bin/testbench` because the fixture app has no `artisan`.

```bash
vendor/bin/testbench manifest:list              # from the repository root
vendor/bin/testbench manifest:types --no-tsconfig
pnpm build && pnpm test:e2e                     # real Vite builds and a dev server
```
