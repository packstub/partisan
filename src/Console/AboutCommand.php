<?php

namespace Packstub\Partisan\Console;

use Illuminate\Console\Command;
use Packstub\Partisan\Console\Concerns\InteractsWithPackage;

class AboutCommand extends Command
{
    use InteractsWithPackage;

    protected $signature = 'partisan:about';

    protected $description = 'Show how partisan mapped the generators onto this package';

    public function handle(): int
    {
        $package = $this->package();

        $this->components->twoColumnDetail('Package root', $package->rootPath);
        $this->components->twoColumnDetail('Root namespace', $package->namespace);
        $this->components->twoColumnDetail('Source path (app_path)', $package->sourcePath);
        $this->components->twoColumnDetail('Database path', $this->laravel->databasePath());
        $this->components->twoColumnDetail('Tests namespace', $package->testsNamespace);
        $this->components->twoColumnDetail('Tests path', $package->testsPath);
        $this->components->twoColumnDetail(
            'Auto-registered providers',
            $package->providers === [] ? '(none declared in composer.json extra.laravel.providers)' : implode(', ', $package->providers)
        );

        return self::SUCCESS;
    }
}
