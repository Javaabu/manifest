---
title: AI assistants
sidebar_position: 1.35
---

The docs are plain Markdown, written for people and AI coding assistants alike. The package ships them, so they always match the version you have installed.

| File | What |
| --- | --- |
| `vendor/javaabu/manifest/llms.txt` | The rules for using the package, and every page |
| `vendor/javaabu/manifest/docs/` | The pages |
| `vendor/javaabu/manifest/resources/boost/` | The Laravel Boost skill and guideline |

# Laravel Boost

[Laravel Boost](https://github.com/laravel/boost) finds the skill and guideline in the Composer package by itself. Run this after installing or updating:

```bash
php artisan boost:update
```

Choose `javaabu/manifest` if Boost asks which third-party packages to use.

The skill (`javaabu-manifest`) covers both sides: apps that use packages' assets, and packages that ship them.

# Other assistants

Point your assistant at the docs from the project's instructions file (`AGENTS.md`, `CLAUDE.md`, `.cursor/rules`, `.github/copilot-instructions.md`):

```md
## Package assets

Laravel packages ship CSS/JS/React/Vue through javaabu/manifest. Before working with package assets, Vite config or Inertia package pages, read vendor/javaabu/manifest/llms.txt.
```

Or copy `vendor/javaabu/manifest/resources/boost/skills/javaabu-manifest` into your assistant's skills folder, such as `.claude/skills/`.
