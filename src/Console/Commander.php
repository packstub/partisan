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
    }

    /**
     * Remap the skeleton application onto the package before Testbench
     * registers providers, then auto-register the package's own providers so
     * its commands are available without a testbench.yaml.
     */
    protected function resolveApplicationCallback()
    {
        $testbench = parent::resolveApplicationCallback();

        return function ($app) use ($testbench) {
            $this->package->applyTo($app);

            $testbench($app);

            if (getenv('PARTISAN_DISCOVER') !== '0') {
                foreach ($this->package->providers as $provider) {
                    if (class_exists($provider)) {
                        $app->register($provider);
                    }
                }
            }
        };
    }
}
