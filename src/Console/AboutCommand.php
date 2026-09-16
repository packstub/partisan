<?php

namespace Packstub\Partisan\Console;

use Illuminate\Console\Command;
use Packstub\Partisan\Agent\AgentMode;
use Packstub\Partisan\Console\Concerns\InteractsWithPackage;

class AboutCommand extends Command
{
    use InteractsWithPackage;

    protected $signature = 'partisan:about';

    protected $description = 'Show how partisan mapped the generators onto this package';

    public function handle(): int
    {
        if (AgentMode::enabled()) {
            $this->dashboard();

            return self::SUCCESS;
        }

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

    /**
     * Content-first dashboard for AI agents: the mapping, the generators
     * available here and the commands to run next, in a few dozen tokens.
     */
    protected function dashboard(): void
    {
        $package = $this->package();
        $root = $package->rootPath;
        $artisan = $package->artisan();
        $relative = fn (string $path): string => str_starts_with($path, $root.DIRECTORY_SEPARATOR) ? substr($path, strlen($root) + 1) : $path;

        $generators = array_keys($this->getApplication()?->all('make') ?? []);
        sort($generators);

        $this->line('partisan: artisan for this package — make: generators write into src/ with the package namespace');
        $this->line('package: '.($package->name === '' ? '(unnamed)' : $package->name));
        $this->line('root: '.$this->tilde($root));
        $this->line('namespace: '.$package->namespace);
        $this->line('source: '.$relative($package->sourcePath));
        $this->line('tests: '.$relative($package->testsPath).' ('.$package->testsNamespace.')');
        $this->line('database: '.$relative($this->laravel->databasePath()));
        $this->line(\sprintf('providers[%d]: %s', count($package->providers), implode(', ', $package->providers)));
        $this->line('artisan: '.$artisan);
        $this->line(\sprintf('generators[%d]: %s', count($generators), implode(', ', $generators)));
        $this->line('help[3]:');
        $this->line(\sprintf('  Run `%s make:<type> <Name>` to generate; the output lists every file written', $artisan));
        $this->line(\sprintf('  Run `%s make:model Invoice --migration --factory --policy` to scaffold companions in one call', $artisan));
        $this->line(\sprintf('  Run `%s make:<type> --help` for a generator\'s options', $artisan));
    }

    protected function tilde(string $path): string
    {
        $home = getenv('HOME');

        return is_string($home) && $home !== '' && str_starts_with($path, $home) ? '~'.substr($path, strlen($home)) : $path;
    }
}
