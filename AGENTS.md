# manifest: notes for AI coding agents

`javaabu/manifest` lets Laravel packages ship CSS, Sass, JS, TS, React and Vue to apps: registration in service providers, automatic injection for Blade, Blade directives, a Vite plugin and Inertia page resolution.

## Rules

- Follow the Javaabu PHP guidelines (`vendor/javaabu/boost-javaabu-guidelines`): snake_case variables and properties, `#[Test]` methods named `it_…`, early returns, no `else`.
- Every change needs tests, and docs in `docs/` linked from `llms.txt`.

## Commands

```bash
composer install
composer lint      # Pint
composer test      # PHPUnit (Testbench)
```
