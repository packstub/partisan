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

## Releasing

- Release = a `## <version>` heading in `CHANGELOG.md`, then a `v<version>` tag on `main`. `.github/workflows/github-release.yml` creates the GitHub release from that changelog section (manual run with a `tag` input for backfills) — no manual release step.
