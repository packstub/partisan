# Changelog

## Unreleased

- README: the Claude Code benchmark redone on 0.5.0 with `--fields`: $0.33 a task in agent mode against $0.62 by hand

## 0.5.0 — 2026-09-17

- `--fields` on `make:model`, `make:migration` and `make:factory`: describe the columns once and all three files agree (#19)
- Foreign keys and morphs add the column, the relationship method and a related factory
- README: a Claude Code benchmark, a Filament resource in 10 turns / $0.30 with agent mode against 18 / $0.85 by hand

## 0.4.0 — 2026-09-16

- `partisan:install --agents` now writes recipes: the exact `make:` commands for your package and which files to edit
- Agent mode: `make:<name> --help` shows only the generator's own options, and a typo shows that usage right away

## 0.3.0 — 2026-09-16

Partisan learns to work with AI coding agents, and to check your package.

- `partisan:check` boots the package and reports what works, one pass / fail line each (#10)
- Agent mode: no prompts or colours, and a list of the files created and updated at the end (#5)
- `partisan:install --agents` tells agents in `AGENTS.md` to use the `make:` generators (#6)
- Generated files are formatted with your package's own Pint (#9)
- `make:filament-resource --generate` works in a package, with the form and table filled from your columns (#8)

## 0.2.0 — 2026-09-07

- Requires PHP 8.3 or newer (was 8.2), like Testbench 11 and Canvas 11 that Partisan builds on

## 0.1.0 — 2026-08-07

First release of Partisan: run every `make:` generator from your package directory and get files with your package's namespace.

Docs: https://packstub.dev/docs/partisan
