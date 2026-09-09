# packstub/partisan

Artisan for Laravel packages — run `make:` generators from a package directory and get files in `src/` with the package namespace.

## Commands

```bash
composer test   # Pest suite
```

## Layout

- `bin/partisan` entry point, `src/` command resolution and stub rewriting, `tests/`.

## Conventions

- Every change needs a test and a `CHANGELOG.md` line.
