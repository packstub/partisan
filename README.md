# Partisan

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

## Filament plugins

If your package targets a Filament panel, declare a panel provider (in `extra.laravel.providers`) whose discovery paths use `app_path()` — partisan remaps `app_path()` to `src/`, so Filament's generators follow along:

```php
$panel->discoverResources(in: app_path('Filament/Resources'), for: 'Acme\\Widget\\Filament\\Resources')
```

Then `make:filament-resource Invoice` produces the resource, pages, schema, and table classes inside `src/Filament/Resources` under your namespace.

## Configuration

Partisan needs none for the common case. It honors your `testbench.yaml` (providers, custom skeleton, migrations, seeders) exactly like the Testbench CLI. Set `PARTISAN_DISCOVER=0` to skip auto-registering the providers from `extra.laravel.providers`.

## Notes

- Models that use factories in a package need the usual `newFactory()` method (or `HasFactory<…>` generic) pointing at your `Database\Factories` namespace — partisan generates the factory in the right place; wiring it to the model remains a one-liner in your model.
- `make:provider` writes the class into `src/Providers`; registering it in your package's service provider chain is up to you.

## Testing

```bash
composer test
```

The suite generates every supported file type into a disposable fixture package and asserts the paths and namespaces, for both Laravel and Filament generators.

## License

MIT
