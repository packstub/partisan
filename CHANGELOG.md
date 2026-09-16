# Changelog

## Unreleased

- `make:filament-resource --generate` (and every other `make:filament-*` generator run with `--generate`) works in a package (#8): the package's migrations are applied to an in-memory SQLite connection before the generator runs, so the form schema and table columns come out populated from the model's columns. Nothing is written to disk.
- Agent mode (#5): when an AI coding agent runs partisan (detected with `laravel/agent-detector`, forced with `PARTISAN_AGENT=1`, disabled with `PARTISAN_AGENT=0`) commands never prompt, drop ANSI, and end with the files they created and updated (`created[6]:` … `updated[1]:` …, `created[0]: no files written` when a generator did nothing) plus a `help[]` hint; `partisan` with no command shows a package dashboard instead of the command list.
- `partisan:install --agents` (#6) appends an "Artisan generators" section to `AGENTS.md` (or `CLAUDE.md` when that is the only instructions file) so agents run `php artisan make:…` instead of writing scaffolding by hand.
- Dev: `laravel/pao` for agent-optimized Pest output when working on partisan with an AI agent.

## 0.2.0 — 2026-09-07

- Requires PHP 8.3 or newer (was 8.2). Orchestra Testbench 11 and Canvas 11, which Partisan builds on, already need 8.3, and the test suite runs on 8.3, 8.4 and 8.5.

## 0.1.0 — 2026-08-07

- First release.
