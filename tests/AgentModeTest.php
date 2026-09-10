<?php

it('lists the files a generator wrote when an AI agent runs partisan', function () {
    $process = partisanWithEnv(['PARTISAN_AGENT' => '1'], 'make:model', 'Invoice', '--migration');

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput())
        ->and($process->getOutput())
        ->toContain("created[2]:\n")
        ->toContain('  - src/Models/Invoice.php')
        ->toMatch('#  - database/migrations/\d{4}_\d{2}_\d{2}_\d{6}_create_invoices_table\.php#')
        ->toContain('help[1]:')
        ->toContain('vendor/bin/partisan make:model --help')
        ->not->toContain("\e[");
});

it('reports every file of a Filament resource', function () {
    $process = partisanWithEnv(['PARTISAN_AGENT' => '1'], 'make:filament-resource', 'Invoice');

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput())
        ->and($process->getOutput())
        ->toContain("created[6]:\n")
        ->toContain('  - src/Filament/Resources/Invoices/InvoiceResource.php')
        ->toContain('  - src/Filament/Resources/Invoices/Schemas/InvoiceForm.php')
        ->toContain('  - src/Filament/Resources/Invoices/Tables/InvoicesTable.php');
});

it('reports updated files next to created ones', function () {
    $process = partisanWithEnv(['PARTISAN_AGENT' => '1'], 'make:command', 'SyncInvoices');

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput())
        ->and($process->getOutput())
        ->toContain("created[1]:\n  - src/Console/SyncInvoices.php")
        ->toContain("updated[1]:\n  - src/WidgetServiceProvider.php");
});

it('gives a definitive empty state when a generator wrote nothing', function () {
    partisanExpectingSuccess('make:model', 'Invoice');

    $process = partisanWithEnv(['PARTISAN_AGENT' => '1'], 'make:model', 'Invoice');

    expect($process->getOutput())->toContain('created[0]: no files written');
});

it('shows the package dashboard instead of the command list when run without a command', function () {
    $process = partisanWithEnv(['PARTISAN_AGENT' => '1']);

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput())
        ->and($process->getOutput())
        ->toContain('package: acme/widget')
        ->toContain('namespace: Acme\Widget\\')
        ->toContain('source: src')
        ->toContain('providers[2]: Acme\Widget\Providers\AdminPanelProvider, Acme\Widget\WidgetServiceProvider')
        ->toContain('artisan: vendor/bin/partisan')
        ->toMatch('/generators\[\d+\]: .*make:filament-resource.*make:model/')
        ->toContain('help[3]:')
        ->not->toContain('Available commands');
});

it('keeps the full command list for people', function () {
    $process = partisan();

    expect($process->getOutput())->toContain('Available commands')
        ->not->toContain('generators[');
});

it('detects the agent from the environment', function () {
    $process = partisanWithEnv(['PARTISAN_AGENT' => false, 'AI_AGENT' => 'claude-code'], 'make:model', 'Invoice');

    expect($process->getOutput())->toContain("created[1]:\n  - src/Models/Invoice.php");
});

it('stays quiet for people', function () {
    $process = partisanWithEnv(['PARTISAN_AGENT' => false, 'AI_AGENT' => false, 'CLAUDECODE' => false], 'make:model', 'Invoice');

    expect($process->getOutput())->not->toContain('created[');
});
