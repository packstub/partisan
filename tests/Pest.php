<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

const PARTISAN_BIN = __DIR__.'/../bin/partisan';
const FIXTURE_TEMPLATE = __DIR__.'/Fixtures/acme-widget';

pest()->beforeEach(function () {
    $this->fixture = sys_get_temp_dir().'/partisan-fixture-'.bin2hex(random_bytes(6));

    (new Filesystem)->copyDirectory(FIXTURE_TEMPLATE, $this->fixture);
    symlink(dirname(__DIR__).'/vendor', $this->fixture.'/vendor');
})->afterEach(function () {
    $filesystem = new Filesystem;

    if (is_link($this->fixture.'/vendor')) {
        unlink($this->fixture.'/vendor');
    }

    $filesystem->deleteDirectory($this->fixture);
})->in(__DIR__);

/**
 * Run bin/partisan inside the test's fixture package.
 */
function partisan(string ...$args): Process
{
    $process = new Process(
        command: [PHP_BINARY, PARTISAN_BIN, ...$args, '--no-interaction'],
        cwd: test()->fixture,
        env: ['TESTBENCH_WORKING_PATH' => false, 'PARTISAN_WORKING_PATH' => false],
        timeout: 120,
    );

    $process->run();

    return $process;
}

function partisanExpectingSuccess(string ...$args): Process
{
    $process = partisan(...$args);

    expect($process->getExitCode())
        ->toBe(0, "partisan {$args[0]} failed:\n".$process->getOutput().$process->getErrorOutput());

    return $process;
}

/**
 * Assert a file was generated in the fixture and contains every needle.
 */
function assertGenerated(string $relativePath, array $contains = []): string
{
    $path = test()->fixture.'/'.$relativePath;

    expect(is_file($path))->toBeTrue("Expected generated file at {$relativePath}");

    $content = (string) file_get_contents($path);

    foreach ($contains as $needle) {
        expect($content)->toContain($needle);
    }

    return $content;
}
