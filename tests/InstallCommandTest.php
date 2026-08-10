<?php

it('creates the artisan entry script with --link', function () {
    partisanExpectingSuccess('partisan:install', '--link');

    assertGenerated('artisan', ['#!/usr/bin/env php', "require __DIR__.'/vendor/bin/partisan'"]);

    expect(is_executable(test()->fixture.'/artisan'))->toBeTrue();
});

it('leaves an existing artisan script alone', function () {
    file_put_contents(test()->fixture.'/artisan', "custom\n");

    $process = partisanExpectingSuccess('partisan:install', '--link');

    expect(file_get_contents(test()->fixture.'/artisan'))->toBe("custom\n")
        ->and($process->getOutput())->toContain('already exists');
});

it('does nothing non-interactively without flags', function () {
    $process = partisanExpectingSuccess('partisan:install');

    expect(is_file(test()->fixture.'/artisan'))->toBeFalse()
        ->and($process->getOutput())->toContain('--link');
});

it('appends the pa function to the shell profile with --alias', function () {
    $home = test()->fixture.'/home';
    mkdir($home);
    $env = ['HOME' => $home, 'SHELL' => '/bin/zsh'];

    $process = partisanWithEnv($env, 'partisan:install', '--alias');

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput())
        ->and((string) file_get_contents($home.'/.zshrc'))
        ->toContain('pa()')
        ->toContain('vendor/bin/partisan');

    // Running again must not duplicate the shortcut.
    $process = partisanWithEnv($env, 'partisan:install', '--alias');

    expect($process->getExitCode())->toBe(0)
        ->and(substr_count((string) file_get_contents($home.'/.zshrc'), 'pa()'))->toBe(1)
        ->and($process->getOutput())->toContain('already defines pa');
});
