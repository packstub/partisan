<?php

namespace Packstub\Partisan;

use Illuminate\Contracts\Foundation\Application;
use Orchestra\Canvas\Core\Presets\Preset;

use function Orchestra\Sidekick\Filesystem\join_paths;

/**
 * Canvas generator preset that targets the package under development: classes
 * land in src/ under the package namespace, database files under database/,
 * tests under tests/ using the autoload-dev namespace.
 */
class GeneratorPreset extends Preset
{
    public function __construct(
        Application $app,
        protected readonly PackageContext $package,
    ) {
        parent::__construct($app);
    }

    public function name(): string
    {
        return 'partisan';
    }

    public function basePath(): string
    {
        return $this->package->rootPath;
    }

    public function sourcePath(): string
    {
        return $this->package->sourcePath;
    }

    public function testingPath(): string
    {
        return $this->package->testsPath;
    }

    public function resourcePath(): string
    {
        return join_paths($this->package->rootPath, 'resources');
    }

    public function viewPath(): string
    {
        return join_paths($this->package->rootPath, 'resources', 'views');
    }

    public function factoryPath(): string
    {
        return join_paths($this->package->rootPath, 'database', 'factories');
    }

    public function migrationPath(): string
    {
        return join_paths($this->package->rootPath, 'database', 'migrations');
    }

    public function seederPath(): string
    {
        return join_paths($this->package->rootPath, 'database', 'seeders');
    }

    public function rootNamespace(): string
    {
        return $this->package->namespace;
    }

    public function commandNamespace(): string
    {
        return "{$this->rootNamespace()}Console\\";
    }

    public function modelNamespace(): string
    {
        return "{$this->rootNamespace()}Models\\";
    }

    public function providerNamespace(): string
    {
        return "{$this->rootNamespace()}Providers\\";
    }

    public function testingNamespace(): string
    {
        return $this->package->testsNamespace;
    }

    public function factoryNamespace(): string
    {
        return "{$this->rootNamespace()}Database\\Factories\\";
    }

    public function seederNamespace(): string
    {
        return "{$this->rootNamespace()}Database\\Seeders\\";
    }

    public function hasCustomStubPath(): bool
    {
        return false;
    }
}
