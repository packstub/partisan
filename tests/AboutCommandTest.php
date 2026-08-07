<?php

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
