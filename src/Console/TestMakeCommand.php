<?php

namespace Packstub\Partisan\Console;

use Orchestra\Canvas\Console\TestMakeCommand as CanvasTestMakeCommand;
use Packstub\Partisan\Console\Concerns\InteractsWithPackage;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:test', description: 'Create a new test class')]
class TestMakeCommand extends CanvasTestMakeCommand
{
    use InteractsWithPackage;

    /**
     * Feature tests generated for a package should extend the package's own
     * TestCase when it has one, falling back to Testbench's, instead of the
     * app-skeleton Tests\TestCase the stub assumes.
     */
    protected function generatingCode($stub, $name)
    {
        $stub = parent::generatingCode($stub, $name);

        if ($this->option('unit') === true) {
            return $stub;
        }

        $package = $this->package();

        $testCase = is_file($package->testsPath.DIRECTORY_SEPARATOR.'TestCase.php')
            ? $package->testsNamespace.'TestCase'
            : 'Orchestra\Testbench\TestCase';

        return str_replace('use Tests\TestCase;', "use {$testCase};", $stub);
    }
}
