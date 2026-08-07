<?php

dataset('laravel generators', [
    'make:cast' => [['make:cast', 'Money'], 'src/Casts/Money.php', ['namespace Acme\Widget\Casts;', 'class Money']],
    'make:channel' => [['make:channel', 'OrderChannel'], 'src/Broadcasting/OrderChannel.php', ['namespace Acme\Widget\Broadcasting;', 'class OrderChannel']],
    'make:class' => [['make:class', 'Support/Helper'], 'src/Support/Helper.php', ['namespace Acme\Widget\Support;', 'class Helper']],
    'make:command' => [['make:command', 'SyncInvoices'], 'src/Console/SyncInvoices.php', ['namespace Acme\Widget\Console;', 'class SyncInvoices']],
    'make:controller' => [['make:controller', 'InvoiceController'], 'src/Http/Controllers/InvoiceController.php', ['namespace Acme\Widget\Http\Controllers;', 'class InvoiceController']],
    'make:enum' => [['make:enum', 'Enums/InvoiceStatus'], 'src/Enums/InvoiceStatus.php', ['namespace Acme\Widget\Enums;', 'enum InvoiceStatus']],
    'make:event' => [['make:event', 'InvoicePaid'], 'src/Events/InvoicePaid.php', ['namespace Acme\Widget\Events;', 'class InvoicePaid']],
    'make:exception' => [['make:exception', 'InvoiceException'], 'src/Exceptions/InvoiceException.php', ['namespace Acme\Widget\Exceptions;', 'class InvoiceException']],
    'make:factory' => [['make:factory', 'InvoiceFactory'], 'database/factories/InvoiceFactory.php', ['namespace Acme\Widget\Database\Factories;', 'class InvoiceFactory extends Factory']],
    'make:interface' => [['make:interface', 'Contracts/Billable'], 'src/Contracts/Billable.php', ['namespace Acme\Widget\Contracts;', 'interface Billable']],
    'make:job' => [['make:job', 'ProcessInvoice'], 'src/Jobs/ProcessInvoice.php', ['namespace Acme\Widget\Jobs;', 'class ProcessInvoice']],
    'make:listener' => [['make:listener', 'SendReceipt'], 'src/Listeners/SendReceipt.php', ['namespace Acme\Widget\Listeners;', 'class SendReceipt']],
    'make:mail' => [['make:mail', 'InvoicePaidMail'], 'src/Mail/InvoicePaidMail.php', ['namespace Acme\Widget\Mail;', 'class InvoicePaidMail']],
    'make:middleware' => [['make:middleware', 'EnsureTenant'], 'src/Http/Middleware/EnsureTenant.php', ['namespace Acme\Widget\Http\Middleware;', 'class EnsureTenant']],
    'make:model' => [['make:model', 'Invoice'], 'src/Models/Invoice.php', ['namespace Acme\Widget\Models;', 'class Invoice extends Model']],
    'make:notification' => [['make:notification', 'InvoicePaidNotification'], 'src/Notifications/InvoicePaidNotification.php', ['namespace Acme\Widget\Notifications;', 'class InvoicePaidNotification']],
    'make:policy' => [['make:policy', 'InvoicePolicy'], 'src/Policies/InvoicePolicy.php', ['namespace Acme\Widget\Policies;', 'class InvoicePolicy']],
    'make:provider' => [['make:provider', 'WidgetServiceProvider'], 'src/Providers/WidgetServiceProvider.php', ['namespace Acme\Widget\Providers;', 'class WidgetServiceProvider']],
    'make:request' => [['make:request', 'StoreInvoiceRequest'], 'src/Http/Requests/StoreInvoiceRequest.php', ['namespace Acme\Widget\Http\Requests;', 'class StoreInvoiceRequest']],
    'make:resource' => [['make:resource', 'InvoiceResource'], 'src/Http/Resources/InvoiceResource.php', ['namespace Acme\Widget\Http\Resources;', 'class InvoiceResource']],
    'make:rule' => [['make:rule', 'Uppercase'], 'src/Rules/Uppercase.php', ['namespace Acme\Widget\Rules;', 'class Uppercase']],
    'make:scope' => [['make:scope', 'ActiveScope'], 'src/Scopes/ActiveScope.php', ['namespace Acme\Widget\Scopes;', 'class ActiveScope']],
    'make:seeder' => [['make:seeder', 'InvoiceSeeder'], 'database/seeders/InvoiceSeeder.php', ['namespace Acme\Widget\Database\Seeders;', 'class InvoiceSeeder extends Seeder']],
    'make:trait' => [['make:trait', 'Concerns/HasMoney'], 'src/Concerns/HasMoney.php', ['namespace Acme\Widget\Concerns;', 'trait HasMoney']],
]);

it('generates into the package', function (array $args, string $path, array $contains) {
    partisanExpectingSuccess(...$args);

    assertGenerated($path, $contains);
})->with('laravel generators');

it('generates migrations into the package database directory', function () {
    partisanExpectingSuccess('make:migration', 'create_invoices_table');

    $migrations = glob(test()->fixture.'/database/migrations/*_create_invoices_table.php');

    expect($migrations)->toHaveCount(1)
        ->and((string) file_get_contents($migrations[0]))->toContain('return new class extends Migration');
});

it('generates observers referencing the package model', function () {
    partisanExpectingSuccess('make:model', 'Invoice');
    partisanExpectingSuccess('make:observer', 'InvoiceObserver', '--model=Invoice');

    assertGenerated('src/Observers/InvoiceObserver.php', [
        'namespace Acme\Widget\Observers;',
        'use Acme\Widget\Models\Invoice;',
    ]);
});

it('generates blade views into the package resources directory', function () {
    partisanExpectingSuccess('make:view', 'invoices.index');

    expect(is_file(test()->fixture.'/resources/views/invoices/index.blade.php'))->toBeTrue();
});

it('generates components with class and view in the package', function () {
    partisanExpectingSuccess('make:component', 'Alert');

    assertGenerated('src/View/Components/Alert.php', ['namespace Acme\Widget\View\Components;', 'class Alert']);

    expect(is_file(test()->fixture.'/resources/views/components/alert.blade.php'))->toBeTrue();
});

it('generates feature tests extending the package TestCase', function () {
    partisanExpectingSuccess('make:test', 'InvoiceTest');

    assertGenerated('tests/Feature/InvoiceTest.php', [
        'namespace Acme\Widget\Tests\Feature;',
        'use Acme\Widget\Tests\TestCase;',
    ]);
});

it('generates feature tests extending the Testbench TestCase when the package has none', function () {
    unlink(test()->fixture.'/tests/TestCase.php');

    partisanExpectingSuccess('make:test', 'InvoiceTest');

    assertGenerated('tests/Feature/InvoiceTest.php', [
        'namespace Acme\Widget\Tests\Feature;',
        'use Orchestra\Testbench\TestCase;',
    ]);
});

it('generates unit tests extending the PHPUnit TestCase', function () {
    partisanExpectingSuccess('make:test', 'PriceTest', '--unit');

    assertGenerated('tests/Unit/PriceTest.php', [
        'namespace Acme\Widget\Tests\Unit;',
        'use PHPUnit\Framework\TestCase;',
    ]);
});

it('generates factories without duplicate imports', function () {
    partisanExpectingSuccess('make:model', 'Invoice');
    partisanExpectingSuccess('make:factory', 'InvoiceFactory', '--model=Invoice');

    $content = assertGenerated('database/factories/InvoiceFactory.php', [
        'namespace Acme\Widget\Database\Factories;',
        'use Acme\Widget\Models\Invoice;',
    ]);

    expect(substr_count($content, 'use Acme\Widget\Models\Invoice;'))->toBe(1);
});
