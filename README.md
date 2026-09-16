# Partisan

![Partisan — Artisan for Laravel packages](https://raw.githubusercontent.com/packstub/art/main/partisan/banner.jpg)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/packstub/partisan.svg?style=flat-square)](https://packagist.org/packages/packstub/partisan)
[![Total Downloads](https://img.shields.io/packagist/dt/packstub/partisan.svg?style=flat-square)](https://packagist.org/packages/packstub/partisan)
[![License](https://img.shields.io/packagist/l/packstub/partisan.svg?style=flat-square)](https://github.com/packstub/partisan/blob/main/LICENSE.md)
[![Sponsor](https://img.shields.io/badge/sponsor-%E2%9D%A4-ea4aaa?style=flat-square&logo=githubsponsors&logoColor=white)](https://github.com/sponsors/icaliman)

**Artisan for Laravel packages.** Run every `make:` generator from your package directory and get files in `src/` with your package's namespace — models, commands, migrations, factories, tests, and full Filament resources included.

Built on [Orchestra Testbench](https://packages.tools/testbench) and [Canvas](https://github.com/orchestral/canvas), the tools that already power Laravel package testing. Partisan adds the missing piece: generators that understand your package is the app.

```bash
composer require --dev packstub/partisan

vendor/bin/partisan make:model Invoice
# → src/Models/Invoice.php with `namespace Acme\Widget\Models;`
```

## What you get

Partisan reads your package's `composer.json` and points the whole generator suite at it:

| Command | Destination | Namespace |
| --- | --- | --- |
| `make:model Invoice` | `src/Models/Invoice.php` | `Acme\Widget\Models` |
| `make:command SyncInvoices` | `src/Console/SyncInvoices.php` | `Acme\Widget\Console` |
| `make:migration create_invoices_table` | `database/migrations/…` | — |
| `make:factory InvoiceFactory` | `database/factories/…` | `Acme\Widget\Database\Factories` |
| `make:seeder InvoiceSeeder` | `database/seeders/…` | `Acme\Widget\Database\Seeders` |
| `make:test InvoiceTest` | `tests/Feature/…` | `Acme\Widget\Tests\Feature` |
| `make:filament-resource Invoice` | `src/Filament/Resources/…` | `Acme\Widget\Filament\Resources\…` |

…and the rest of the `make:` family (casts, controllers, events, jobs, listeners, mail, middleware, notifications, policies, providers, requests, rules, views, components, enums, interfaces, traits, and more). Generated feature tests extend your package's own `tests/TestCase.php` when you have one, or `Orchestra\Testbench\TestCase` otherwise.

Because partisan wraps the Testbench CLI, everything Testbench can do still works — `vendor/bin/partisan serve`, `migrate`, `route:list`, your package's own commands, `workbench:install`, all of it.

## How it works

Testbench boots a skeleton Laravel application around your package; Canvas routes every generator through a pluggable *generator preset*. Partisan registers a preset that maps the generator targets onto your package:

- `autoload.psr-4` → source path and root namespace
- `autoload-dev.psr-4` → tests path and namespace
- `database/`, `resources/` → database and view targets
- `extra.laravel.providers` → auto-registered, so your package's own artisan commands are available too

The `workbench` and `laravel` presets stay available per command (`make:model Demo --preset=workbench`) when you want to generate into your Workbench demo app instead.

Generated files match your package, not just its namespace: when `laravel/pint` is installed, every file a generator creates or updates is run through it with your `pint.json` before you see it, so a `declare_strict_types` rule, import order or docblock class names come out the way your CI expects. Set `PARTISAN_PINT=0` to skip that.

Run `vendor/bin/partisan partisan:about` to see exactly how your package was mapped.

## A shorter command

`vendor/bin/partisan` is a lot of typing for a tool you reach for constantly. The installer sets up the shortcuts below (and, see [AI coding agents](#ai-coding-agents), an `AGENTS.md` section) — each step is a prompt you can decline:

```bash
vendor/bin/partisan partisan:install
```

In scripts and CI (`--no-interaction`) it only does what you flag explicitly: `--link`, `--alias`, `--agents`.

### `php artisan`, in your package

The installer's first offer is an `artisan` script in your package root — a two-line stub that forwards to `vendor/bin/partisan`:

```php
#!/usr/bin/env php
<?php require __DIR__.'/vendor/bin/partisan';
```

With it, the muscle-memory commands work in your package exactly like in an app:

```bash
php artisan make:model Invoice
./artisan make:filament-resource Invoice
```

A plain PHP file works the same on macOS, Linux, and Windows, and survives archives and checkouts without symlink support — but if you prefer the classic look, a symlink does the job too: `ln -s vendor/bin/partisan artisan`. Commit the script so every contributor gets it, or let the installer add it to `.gitignore` if you'd rather keep it personal.

### A global `pa` shortcut

The second offer is a `pa` shortcut appended to your shell profile (zsh, bash, and fish are detected). To set it up by hand instead, add an alias:

```bash
# ~/.zshrc or ~/.bashrc
alias pa="vendor/bin/partisan"
```

Then, from your package root:

```bash
pa make:model Invoice
```

If you often work from subdirectories, use a function that finds the nearest `vendor/bin/partisan` upward instead (this is the variant the installer offers first):

```bash
pa() {
  local dir=$PWD
  while [ "$dir" != "/" ]; do
    [ -x "$dir/vendor/bin/partisan" ] && { "$dir/vendor/bin/partisan" "$@"; return; }
    dir=$(dirname "$dir")
  done
  echo "no vendor/bin/partisan here — composer require --dev packstub/partisan" >&2
  return 1
}
```

## Filament plugins

If your package targets a Filament panel, declare a panel provider (in `extra.laravel.providers`) whose discovery paths use `app_path()` — partisan remaps `app_path()` to `src/`, so Filament's generators follow along:

```php
$panel->discoverResources(in: app_path('Filament/Resources'), for: 'Acme\\Widget\\Filament\\Resources')
```

Then `make:filament-resource Invoice` produces the resource, pages, schema, and table classes inside `src/Filament/Resources` under your namespace.

Add `--generate` and the form and table come out populated from your model's columns, exactly as in an app. A package has no database for Filament to read, so partisan applies the package's migrations (`database/migrations`, `workbench/database/migrations`, and any path a provider or `testbench.yaml` registers) to an in-memory SQLite connection first; nothing is written to disk and no `.env` is needed.

## Checking the package

After a change, one command answers "does it still boot and is the new thing wired?":

```bash
vendor/bin/partisan partisan:check
```

It boots the package and reports, one line each: the providers declared in `composer.json` are registered; the commands and routes the package contributes; Filament panels with their resource and page counts; the package's migrations applied to an in-memory SQLite; `pint --test` on `src/` and `database/` when Pint is installed; and whether every class under the package's autoload paths (plus `database/factories` and `database/seeders`) can actually be loaded, which catches a factories directory nobody mapped in `autoload-dev`. The exit code is 1 when anything fails, so it works as a CI step too.

## AI coding agents

Partisan notices when an AI coding agent is driving it (Claude Code, Codex, Cursor, Gemini CLI, Copilot and friends, via [laravel/agent-detector](https://github.com/laravel/agent-detector)) and switches to output shaped for a model instead of a terminal, following the [AXI](https://axi.md) principles for agent-ergonomic CLIs:

- **Never prompts, no ANSI.** Every command runs non-interactively; questions take their defaults.
- **Lists every file it wrote.** A generator ends with the files it created and updated, so the agent never spends a turn on `find` to learn what appeared:

  ```
  created[6]:
    - src/Filament/Resources/Invoices/InvoiceResource.php
    - src/Filament/Resources/Invoices/Pages/CreateInvoice.php
    - src/Filament/Resources/Invoices/Pages/EditInvoice.php
    - src/Filament/Resources/Invoices/Pages/ListInvoices.php
    - src/Filament/Resources/Invoices/Schemas/InvoiceForm.php
    - src/Filament/Resources/Invoices/Tables/InvoicesTable.php
  help[1]:
    Run `php artisan make:filament-resource --help` for this generator's options
  ```

  A generator that wrote nothing says so (`created[0]: no files written`) instead of leaving the agent to guess.
- **Content first.** `php artisan` with no command shows the package dashboard — name, namespace, paths, providers, the generators available and the commands to run next — instead of Laravel's 200-line command list.

Set `PARTISAN_AGENT=1` to get the same output in your own terminal, or `PARTISAN_AGENT=0` to opt out.

The other half is making sure the agent reaches for the generator at all. `partisan:install --agents` appends a short "Artisan generators" section to your package's `AGENTS.md` (or `CLAUDE.md` when that is the only instructions file) telling agents that `php artisan make:…` works here and to run it before writing scaffolding by hand. Pair it with [laravel/pao](https://github.com/laravel/pao) for agent-optimized Pest and PHPStan output and the whole package loop stays cheap to read.

## Configuration

Partisan needs none for the common case. It honors your `testbench.yaml` (providers, custom skeleton, migrations, seeders) exactly like the Testbench CLI. Set `PARTISAN_DISCOVER=0` to skip auto-registering the providers from `extra.laravel.providers`.

## Notes

- `make:command SyncInvoices` offers to register the new command in your package's service provider (automatic under `--no-interaction`, opt out with `--no-register`). It inserts into an existing `$this->commands([...])` or spatie-style `->hasCommands([...])` block — adding the import, never duplicating an entry — and appends a fresh `$this->commands([...])` block to `boot()` when the provider has neither.
- `make:model Invoice --factory` wires the model to its package factory automatically: the `HasFactory` docblock points at your `Database\Factories` namespace and a `#[UseFactory(InvoiceFactory::class)]` attribute is added, so `Invoice::factory()` resolves at runtime with no manual `newFactory()`. If a future Laravel stub ships its own factory wiring, partisan detects it and only corrects the namespace.
- `make:provider` writes the class into `src/Providers`; registering it in your package's service provider chain is up to you.

## Testing

```bash
composer test
```

The suite generates every supported file type into a disposable fixture package and asserts the paths and namespaces, for both Laravel and Filament generators.

## License

MIT
