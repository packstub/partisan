# packstub/partisan

Artisan for Laravel packages — run `make:` generators from a package directory and get files in `src/` with the package namespace.

## Commands

```bash
composer test   # Pest suite
```

## Layout

- `bin/partisan` entry point, `src/` command resolution and stub rewriting, `src/Agent/` agent mode (detection, file report, dashboard), `tests/`.
- Tests run partisan as a subprocess with `PARTISAN_AGENT=0`; agent-mode tests set it to `1` explicitly.

## Conventions

- Every change needs a test and a `CHANGELOG.md` line under `## Unreleased`: one short, plain line on what a user gets or what changed (a class or method name only when the user needs it, never a list of them; no CI, tests or refactors). A release is an optional lead sentence and at most ~6 such bullets; breaking changes and migrations get an `**Upgrading:**` line saying what to do, and a security fix says so. The release workflow puts that section in the GitHub release and appends the merged pull requests under it.
