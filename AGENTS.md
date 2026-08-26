# packstub/partisan

Artisan for Laravel packages — run `make:` generators from a package directory and get files in `src/` with the package namespace. Free, on Packagist.

## Commands

```bash
composer test   # Pest suite
```

## Layout

- `bin/partisan` entry point, `src/` command resolution and stub rewriting, `tests/`.
- Used as a path dependency by `../../testing/packstub-tests/demo-plugin`.

## Conventions

- PHP 8.3+, Pint, Pest; every change needs a test. Listing copy via the `filament-plugin-listing` skill (workspace root).
