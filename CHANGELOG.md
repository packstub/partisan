# Changelog

## 0.3.0 — 2026-09-16

- `partisan:check` (#10) boots the package and reports, one pass/fail line each, the providers from `composer.json`, the commands and routes the package registers, Filament panels with their resource and page counts, the migrations applied to an in-memory SQLite, `pint --test` on `src/` and `database/`, and whether every class under the package paths (plus `database/factories` and `database/seeders`) is autoloadable. Exit code 1 when anything fails; agent mode prints `help[n]` lines with the fix for each failure.
- Files a generator creates or updates are formatted with the package's own Pint (`vendor/bin/pint`, with the package's `pint.json`) before they are reported (#9), so a `declare_strict_types` rule, import order and docblock class names match the package from the start. Silent when Pint is not installed; `PARTISAN_PINT=0` opts out.
- `make:filament-resource --generate` (and every other `make:filament-*` generator run with `--generate`) works in a package (#8): the package's migrations are applied to an in-memory SQLite connection before the generator runs, so the form schema and table columns come out populated from the model's columns. Nothing is written to disk.
- Agent mode (#5): when an AI coding agent runs partisan (detected with `laravel/agent-detector`, forced with `PARTISAN_AGENT=1`, disabled with `PARTISAN_AGENT=0`) commands never prompt, drop ANSI, and end with the files they created and updated (`created[6]:` … `updated[1]:` …, `created[0]: no files written` when a generator did nothing) plus a `help[]` hint; `partisan` with no command shows a package dashboard instead of the command list.
- `partisan:install --agents` (#6) appends an "Artisan generators" section to `AGENTS.md` (or `CLAUDE.md` when that is the only instructions file) so agents run `php artisan make:…` instead of writing scaffolding by hand.
- Dev: `laravel/pao` for agent-optimized Pest output when working on partisan with an AI agent.

## 0.2.0 — 2026-09-07

- Requires PHP 8.3 or newer (was 8.2). Orchestra Testbench 11 and Canvas 11, which Partisan builds on, already need 8.3, and the test suite runs on 8.3, 8.4 and 8.5.

## 0.1.0 — 2026-08-07

- First release.
