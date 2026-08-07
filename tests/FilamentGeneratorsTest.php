<?php

it('generates Filament resources into the package using the panel discovery paths', function () {
    partisanExpectingSuccess('make:filament-resource', 'Invoice');

    assertGenerated('src/Filament/Resources/Invoices/InvoiceResource.php', [
        'namespace Acme\Widget\Filament\Resources\Invoices;',
        'class InvoiceResource',
    ]);
    assertGenerated('src/Filament/Resources/Invoices/Pages/ListInvoices.php', [
        'namespace Acme\Widget\Filament\Resources\Invoices\Pages;',
    ]);
    assertGenerated('src/Filament/Resources/Invoices/Schemas/InvoiceForm.php', [
        'namespace Acme\Widget\Filament\Resources\Invoices\Schemas;',
    ]);
    assertGenerated('src/Filament/Resources/Invoices/Tables/InvoicesTable.php', [
        'namespace Acme\Widget\Filament\Resources\Invoices\Tables;',
    ]);
});

it('generates Filament pages into the package', function () {
    partisanExpectingSuccess('make:filament-page', 'Settings');

    assertGenerated('src/Filament/Pages/Settings.php', [
        'namespace Acme\Widget\Filament\Pages;',
        'class Settings',
    ]);
});

it('generates Filament widgets into the package', function () {
    partisanExpectingSuccess('make:filament-widget', 'StatsOverview');

    assertGenerated('src/Filament/Widgets/StatsOverview.php', [
        'namespace Acme\Widget\Filament\Widgets;',
        'class StatsOverview',
    ]);
});
