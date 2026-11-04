---
title: Dhivehi and RTL
sidebar_position: 9
---

# Translations

Every message the package shows (command output) is translatable, in English (`lang/en`) and Dhivehi (`lang/dv`). The commands use the app's locale:

```php
app()->setLocale('dv');
```

```bash
php artisan manifest:list
```

```
| އެސެޓް | ބާވަތް | އެންޓްރީ | ބަރޯސާވަނީ |
...
   INFO  5 އެސެޓް، ބަރޯސާވާ ތަރުތީބުން.
```

Publish the files to change them:

```bash
php artisan vendor:publish --tag="manifest-translations"
```

Exception messages are for developers and stay in English, like Laravel's.

## Translations to review

These Dhivehi lines are technical terms with no settled translation. A native speaker should review them:

| Key | Dhivehi | English |
| --- | --- | --- |
| `list.asset` | އެސެޓް | Asset |
| `list.entry` | އެންޓްރީ | Entry |
| `list.depends_on` | ބަރޯސާވަނީ | Depends on |
| `list.override` | އޯވަރައިޑް | override |
| `check.needs` | ބޭނުންވާ ވާޝަން | Needs |
| `override.copied` | … ބަދަލުތައް ގެންނާނީ އެތަނުގައި … | Edit it there … |

# RTL

Manifest doesn't render UI, so it has no direction of its own. It loads whatever packages register, and leaves the page's `lang` and `dir` alone. Package authors should write styles that work in both directions:

- logical properties and utilities (`margin-inline-start`, `padding-inline`, `ms-2`, `pe-4`, `start-0`), never `left` / `right`,
- `dir="rtl"` from the page, not hard-coded in components,
- strings through `@javaabu/i18n` in JS, and `__()` in PHP.

The reference packages in the tests use logical properties and Thaana text.
