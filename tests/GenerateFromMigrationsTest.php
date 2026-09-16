<?php

function invoiceModelWithMigration(): void
{
    partisanExpectingSuccess('make:model', 'Invoice', '--migration');

    [$migration] = glob(test()->fixture.'/database/migrations/*_create_invoices_table.php');

    file_put_contents($migration, <<<'PHP'
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('invoices', function (Blueprint $table) {
                    $table->id();
                    $table->string('number');
                    $table->decimal('total', 10, 2)->nullable();
                    $table->boolean('is_paid')->default(false);
                    $table->text('notes')->nullable();
                    $table->timestamps();
                });
            }
        };
        PHP);
}

it('fills the form and table from the package migrations with --generate', function () {
    invoiceModelWithMigration();

    $process = partisanExpectingSuccess('make:filament-resource', 'Invoice', '--generate');

    expect($process->getOutput())->toContain('Applied 1 package migration to an in-memory database');

    assertGenerated('src/Filament/Resources/Invoices/Schemas/InvoiceForm.php', [
        "TextInput::make('number')",
        "TextInput::make('total')",
        "Toggle::make('is_paid')",
        "Textarea::make('notes')",
    ]);
    assertGenerated('src/Filament/Resources/Invoices/Tables/InvoicesTable.php', [
        "TextColumn::make('number')",
        "IconColumn::make('is_paid')",
    ]);

    expect(is_file(test()->fixture.'/database/database.sqlite'))->toBeFalse();
});

it('still generates the resource when the package has no migrations', function () {
    partisanExpectingSuccess('make:model', 'Invoice');

    $process = partisanExpectingSuccess('make:filament-resource', 'Invoice', '--generate');

    expect($process->getOutput())->toContain('Applied 0 package migrations');

    assertGenerated('src/Filament/Resources/Invoices/Schemas/InvoiceForm.php', ['class InvoiceForm']);
});

it('reports the migration run in agent mode', function () {
    invoiceModelWithMigration();

    $process = partisanWithEnv(['PARTISAN_AGENT' => '1'], 'make:filament-resource', 'Invoice', '--generate');

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput())
        ->and($process->getOutput())
        ->toContain('migrated[1]: package migrations applied to an in-memory sqlite')
        ->toContain("created[6]:\n");
});

it('leaves generators without --generate untouched by the database', function () {
    invoiceModelWithMigration();

    $process = partisanExpectingSuccess('make:filament-resource', 'Invoice');

    expect($process->getOutput())->not->toContain('in-memory');
});
