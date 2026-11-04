# manifest: notes for AI coding agents

`javaabu/manifest` lets Laravel packages ship CSS, Sass, JS, TS, React and Vue to apps: registration in service providers, automatic injection for Blade, Blade directives, a Vite plugin with import aliases and overrides, Inertia page resolution, and prebuilt dist fallback. It's a Composer package that also ships JS (`dist/`, built from `js/src`).

## Layout

| Path | What |
| --- | --- |
| `src/Manifest.php` | The registry: packages, dependency resolution, requiring, overrides, paths, pages, discovery data |
| `src/Package.php`, `src/Asset.php`, `src/Enums/AssetTypes.php` | The registration API and its values |
| `src/Concerns/RegistersAssets.php` | The service provider trait |
| `src/AssetRequirements.php` | What the current request needs (scoped binding) |
| `src/Rendering/Injector.php` | Fills directive placeholders and injects tags |
| `src/Rendering/TagRenderer.php`, `DistVite.php` | Tags through Laravel's `Vite`; prebuilt dist |
| `src/Http/` | The injection middleware and the dist route's controller |
| `src/Commands/` | `manifest:list`, `check`, `discover`, `types`, `override` |
| `src/Testing/` | `Manifest::fake()` |
| `js/src/vite.ts` | The Vite plugin; `resolution.ts`, `discovery.ts`, `pages.ts` are its parts |
| `js/src/inertia.ts`, `js/src/package.ts` | `@javaabu/manifest/inertia`, `@javaabu/manifest/package` |
| `dist/` | Built JS, **committed** (apps import it from vendor/). CI fails if it's stale |
| `tests/Fixtures/app` | The Laravel app tests run in (Testbench base path), with reference packages in `vendor/acme` |
| `tests/e2e` | Real Vite builds and a dev server against the fixture app |
| `docs`, `llms.txt` | Docusaurus pages and the index for assistants, shipped in the package |
| `resources/boost` | Laravel Boost guideline and skill |

## Rules

- Follow the Javaabu PHP guidelines (`vendor/javaabu/boost-javaabu-guidelines`): snake_case variables and properties, `#[Test]` methods named `it_…`, early returns, no `else`, imports instead of FQNs in docblocks.
- Never render tags yourself: go through Laravel's `Vite` (`TagRenderer`), so nonces, integrity and dev server keep working.
- Paths the plugin and PHP share are relative to the app root. Symlinked (path repository) packages map back to `vendor/` on both sides.
- PHP and the plugin apply the same override rule; change both together.
- After changing `js/src`, run `pnpm build` and commit `dist/`.
- After changing fixture pages, run `vendor/bin/testbench manifest:types --no-tsconfig` and commit `tests/Fixtures/app/resources/js/types/manifest.d.ts`.
- Every user-facing string goes in `lang/en` and `lang/dv`; tests use Thaana for user-facing behaviour.
- Every change needs tests, a page in `docs` linked from `llms.txt`, and the Boost skill if it changes how the package is used. Add a line to `CHANGELOG.md`.

## Commands

```bash
composer install && pnpm install
composer lint && composer test
pnpm lint && pnpm format:check && pnpm typecheck
pnpm build
pnpm test:coverage                 # unit + e2e (needs PHP and Composer deps)
vendor/bin/phpunit --group e2e     # after the e2e build
vendor/bin/testbench manifest:list # artisan in the fixture app
```
