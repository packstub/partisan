<?php

it('passes on a healthy package and reports every item', function () {
    $process = partisanWithEnv(['PARTISAN_AGENT' => '1'], 'partisan:check');

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput())
        ->and($process->getOutput())
        ->toContain('providers: pass — 2 registered (AdminPanelProvider, WidgetServiceProvider)')
        ->toContain('commands: pass — 1 registered (widget:existing)')
        ->toContain('routes: pass')
        ->toContain('filament: pass — admin: 0 resources, 0 pages')
        ->toContain('migrations: pass — 0 applied to an in-memory sqlite')
        ->toContain('pint: ')
        ->toContain('autoload: pass')
        ->not->toContain('help[');
});

it('renders PASS and FAIL columns for people', function () {
    $process = partisanExpectingSuccess('partisan:check');

    expect($process->getOutput())->toContain('providers')->toContain('PASS')->not->toContain('providers: pass');
});

it('counts Filament resources and package routes once a resource exists', function () {
    partisanExpectingSuccess('make:model', 'Invoice');
    partisanExpectingSuccess('make:filament-resource', 'Invoice');

    $process = partisanWithEnv(['PARTISAN_AGENT' => '1'], 'partisan:check');

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput())
        ->and($process->getOutput())
        ->toContain('filament: pass — admin: 1 resources, 0 pages')
        ->toContain('routes: pass — 3 point at the package');
});

it('applies the package migrations', function () {
    partisanExpectingSuccess('make:model', 'Invoice', '--migration');

    $process = partisanWithEnv(['PARTISAN_AGENT' => '1'], 'partisan:check');

    expect($process->getOutput())->toContain('migrations: pass — 1 applied');
});

it('fails with the exception when a provider throws while booting', function () {
    file_put_contents(test()->fixture.'/src/WidgetServiceProvider.php', <<<'PHP'
        <?php

        namespace Acme\Widget;

        use Illuminate\Support\ServiceProvider;

        class WidgetServiceProvider extends ServiceProvider
        {
            public function boot(): void
            {
                throw new \RuntimeException('widget boot failed');
            }
        }
        PHP);

    $process = partisan('partisan:check');

    expect($process->getExitCode())->not->toBe(0)
        ->and($process->getOutput().$process->getErrorOutput())->toContain('widget boot failed');
});

it('fails autoload for a factory whose namespace composer does not map', function () {
    mkdir(test()->fixture.'/database/factories', 0755, true);
    file_put_contents(test()->fixture.'/database/factories/InvoiceFactory.php', <<<'PHP'
        <?php

        namespace Acme\Widget\Database\Factories;

        use Illuminate\Database\Eloquent\Factories\Factory;

        class InvoiceFactory extends Factory
        {
        }
        PHP);

    $process = partisanWithEnv(['PARTISAN_AGENT' => '1'], 'partisan:check');

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())
        ->toContain('autoload: fail — Acme\Widget\Database\Factories\InvoiceFactory is not covered by composer.json autoload (database/factories/InvoiceFactory.php)')
        ->toMatch('/help\[\d\]:/')
        ->toContain('autoload-dev');
});

it('fails autoload when a class does not match its path', function () {
    file_put_contents(test()->fixture.'/src/Console/Misplaced.php', "<?php\n\nnamespace Acme\\Widget\\Support;\n\nclass Misplaced\n{\n}\n");

    $process = partisanWithEnv(['PARTISAN_AGENT' => '1'], 'partisan:check');

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('src/Console/Misplaced.php declares Acme\Widget\Support\Misplaced but its path maps to Acme\Widget\Console\Misplaced');
});

it('reports the files pint would change', function () {
    file_put_contents(test()->fixture.'/pint.json', '{"preset":"laravel","rules":{"declare_strict_types":true}}');

    $process = partisanWithEnv(['PARTISAN_AGENT' => '1'], 'partisan:check');

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())
        ->toMatch('/pint: fail — 3 files need formatting: .*WidgetServiceProvider\.php/')
        ->toContain('Run `vendor/bin/pint src`');
});
