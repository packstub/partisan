<?php

function fixturePintJson(array $rules = ['declare_strict_types' => true]): void
{
    file_put_contents(test()->fixture.'/pint.json', json_encode(['preset' => 'laravel', 'rules' => $rules], JSON_PRETTY_PRINT));
}

function pintTest(string ...$paths): \Symfony\Component\Process\Process
{
    $process = new \Symfony\Component\Process\Process([PHP_BINARY, 'vendor/bin/pint', '--test', ...$paths], test()->fixture, timeout: 120);
    $process->run();

    return $process;
}

it('formats generated files with the package pint.json', function () {
    fixturePintJson();

    partisanExpectingSuccess('make:model', 'Invoice', '--migration', '--factory', '--policy');

    expect(assertGenerated('src/Models/Invoice.php'))->toStartWith("<?php\n\ndeclare(strict_types=1);\n")
        ->and(assertGenerated('src/Policies/InvoicePolicy.php'))->toContain('declare(strict_types=1);')
        ->and(assertGenerated('database/factories/InvoiceFactory.php'))->toContain('declare(strict_types=1);')
        ->and(pintTest('src/Models', 'src/Policies', 'database')->getExitCode())->toBe(0);
});

it('formats the service provider a generator updated', function () {
    fixturePintJson();

    partisanExpectingSuccess('make:command', 'SyncInvoices');

    expect(pintTest('src/WidgetServiceProvider.php', 'src/Console/SyncInvoices.php')->getExitCode())->toBe(0);
});

it('leaves the stub output untouched with PARTISAN_PINT=0', function () {
    fixturePintJson();

    $process = partisanWithEnv(['PARTISAN_PINT' => '0'], 'make:model', 'Invoice');

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput())
        ->and(assertGenerated('src/Models/Invoice.php'))->not->toContain('declare(strict_types=1);');
});

it('reports the formatting in agent mode before the file list', function () {
    fixturePintJson();

    $process = partisanWithEnv(['PARTISAN_AGENT' => '1'], 'make:model', 'Invoice', '--migration');

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput())
        ->and($process->getOutput())
        ->toMatch("/formatted\[2\]: pint, with the package's pint\.json\n.*created\[2\]:/s");
});

it('stays quiet for people when formatting succeeds', function () {
    fixturePintJson();

    expect(partisanExpectingSuccess('make:model', 'Invoice')->getOutput())->not->toContain('formatted');
});
