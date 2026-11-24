<?php

use Symfony\Component\Process\Process;

it('reports the resolved package mapping', function () {
    $process = partisanExpectingSuccess('partisan:about');

    expect($process->getOutput())
        ->toContain('Acme\Widget\\')
        ->toContain('src')
        ->toContain('Acme\Widget\Tests\\')
        ->toContain('Acme\Widget\Providers\AdminPanelProvider');
});

it('fails with a helpful error outside a package directory', function () {
    unlink(test()->fixture.'/composer.json');

    $process = partisan('partisan:about');

    expect($process->getExitCode())->not->toBe(0)
        ->and($process->getErrorOutput())->toContain('composer.json');
});

it('works on the package PARTISAN_WORKING_PATH names, from any directory', function () {
    $process = new Process(
        command: [PHP_BINARY, PARTISAN_BIN, 'make:model', 'Invoice', '--no-interaction'],
        cwd: sys_get_temp_dir(),
        env: ['TESTBENCH_WORKING_PATH' => false, 'PARTISAN_WORKING_PATH' => test()->fixture, 'PARTISAN_AGENT' => '0'],
        timeout: 120,
    );

    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput())
        ->and(test()->fixture.'/src/Models/Invoice.php')->toBeFile();
});
