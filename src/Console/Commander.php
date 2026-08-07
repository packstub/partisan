<?php

namespace Packstub\Partisan\Console;

use Orchestra\Testbench\Console\Commander as TestbenchCommander;
use Orchestra\Testbench\Foundation\Config;
use Orchestra\Testbench\Foundation\TestbenchServiceProvider;
use Packstub\Partisan\PackageContext;
use Packstub\Partisan\PartisanServiceProvider;

class Commander extends TestbenchCommander
{
    /** @var array<int, class-string<\Illuminate\Support\ServiceProvider>> */
    protected array $providers = [
        TestbenchServiceProvider::class,
        PartisanServiceProvider::class,
    ];

    public function __construct(
        Config|array $config,
        string $workingPath,
        protected readonly PackageContext $package,
    ) {
        parent::__construct($config, $workingPath);

        // The package's own providers (composer.json extra.laravel.providers)
        // go through Testbench's config so they register at the same
        // bootstrap phase as testbench.yaml providers — early enough for
        // their commands, late enough for config/mergeConfigFrom to work.
        if (getenv('PARTISAN_DISCOVER') !== '0') {
            // Merged by hand instead of Config::addProviders(): a commented-out
            // providers list in testbench.yaml parses as null, which the
            // helper doesn't tolerate.
            $this->config['providers'] = array_values(array_unique(array_merge(
                (array) $this->config['providers'],
                array_filter($this->package->providers, static function (string $provider): bool {
                    try {
                        return class_exists($provider);
                    } catch (\Throwable) {
                        // e.g. a provider extending a class from an uninstalled
                        // optional dependency — skip it rather than crash.
                        return false;
                    }
                }),
            )));
        }
    }

    /**
     * Remap the skeleton application onto the package before Testbench
     * registers providers.
     */
    protected function resolveApplicationCallback()
    {
        $testbench = parent::resolveApplicationCallback();

        return function ($app) use ($testbench) {
            $this->package->applyTo($app);

            $testbench($app);
        };
    }
}
