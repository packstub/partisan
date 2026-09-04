# Partisan

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

Run `vendor/bin/partisan partisan:about` to see exactly how your package was mapped.

## A shorter command

`vendor/bin/partisan` is a lot of typing for a tool you reach for constantly. The installer sets up either (or both) of the shortcuts below — each step is a prompt you can decline:

```bash
vendor/bin/partisan partisan:install
```

In scripts and CI (`--no-interaction`) it only does what you flag explicitly: `--link`, `--alias`.

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
