<?php

namespace Packstub\Partisan\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Routing\Router;
use Packstub\Partisan\Agent\AgentMode;
use Packstub\Partisan\Console\Concerns\InteractsWithPackage;
use Packstub\Partisan\Database\PackageDatabase;
use Packstub\Partisan\Support\AutoloadAudit;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * One command answering "does the package still boot and is the new thing
 * wired?": providers, commands, routes, Filament resources, migrations, Pint
 * and autoload, one pass/fail line each. Exit code 1 when anything fails.
 */
class CheckCommand extends Command
{
    use InteractsWithPackage;

    protected $signature = 'partisan:check';

    protected $description = 'Boot the package and report providers, commands, routes, resources, migrations, Pint and autoload';

    /** @var list<array{key: string, status: 'pass'|'fail'|'skip', detail: string, help: string|null}> */
    protected array $items = [];

    public function handle(): int
    {
        $this->providers();
        $this->commands();
        $this->routes();
        $this->filament();
        $this->migrations();
        $this->pint();
        $this->autoload();

        $this->render();

        return in_array('fail', array_column($this->items, 'status'), true) ? self::FAILURE : self::SUCCESS;
    }

    protected function providers(): void
    {
        $declared = $this->package()->providers;

        if ($declared === []) {
            $this->item('providers', 'skip', 'none declared in composer.json extra.laravel.providers');

            return;
        }

        $missing = [];

        foreach ($declared as $provider) {
            try {
                if (! class_exists($provider) || $this->laravel->getProvider($provider) === null) {
                    $missing[] = $provider;
                }
            } catch (Throwable $error) {
                $missing[] = $provider.' ('.$error->getMessage().')';
            }
        }

        if ($missing !== []) {
            $this->item('providers', 'fail', 'not registered: '.implode(', ', $missing), 'Check the class names in composer.json extra.laravel.providers and that each class is autoloadable');

            return;
        }

        $this->item('providers', 'pass', \sprintf('%d registered (%s)', count($declared), implode(', ', array_map('class_basename', $declared))));
    }

    protected function commands(): void
    {
        $namespace = $this->package()->namespace;
        $names = [];

        foreach ($this->getApplication()?->all() ?? [] as $name => $command) {
            if (str_starts_with($command::class, $namespace) && ! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        sort($names);

        $this->item('commands', 'pass', $names === [] ? 'none registered by the package' : \sprintf('%d registered (%s)', count($names), implode(', ', $names)));
    }

    protected function routes(): void
    {
        $namespace = $this->package()->namespace;

        try {
            /** @var Router $router */
            $router = $this->laravel->make(Registrar::class);
            $count = 0;

            foreach ($router->getRoutes()->getRoutes() as $route) {
                $action = $route->getAction();
                $target = implode(' ', array_filter([
                    is_string($action['uses'] ?? null) ? $action['uses'] : null,
                    is_string($action['controller'] ?? null) ? $action['controller'] : null,
                ]));

                if (str_contains($target, $namespace)) {
                    $count++;
                }
            }
        } catch (Throwable $error) {
            $this->item('routes', 'fail', $error->getMessage(), 'Fix the route file or provider the error points at');

            return;
        }

        $this->item('routes', 'pass', $count === 0 ? 'none point at the package' : \sprintf('%d point at the package', $count));
    }

    protected function filament(): void
    {
        if (! class_exists(\Filament\Facades\Filament::class)) {
            $this->item('filament', 'skip', 'filament/filament is not installed');

            return;
        }

        try {
            $panels = [];

            foreach (\Filament\Facades\Filament::getPanels() as $panel) {
                $panels[] = \sprintf('%s: %d resources, %d pages', $panel->getId(), count($panel->getResources()), count($panel->getPages()));
            }
        } catch (Throwable $error) {
            $this->item('filament', 'fail', $error->getMessage(), 'A resource or page class failed to load; the message names it');

            return;
        }

        $this->item('filament', $panels === [] ? 'skip' : 'pass', $panels === [] ? 'no panel registered' : implode('; ', $panels));
    }

    protected function migrations(): void
    {
        $result = (new PackageDatabase($this->laravel, $this->package()))->migrate();

        if ($result['error'] !== null) {
            $this->item('migrations', 'fail', $result['error'], 'The migration named in the message did not run against SQLite');

            return;
        }

        $this->item('migrations', 'pass', \sprintf('%d applied to an in-memory sqlite', $result['migrated']));
    }

    protected function pint(): void
    {
        $root = $this->package()->rootPath;
        $pint = $root.'/vendor/bin/pint';

        if (! is_file($pint)) {
            $this->item('pint', 'skip', 'laravel/pint is not installed');

            return;
        }

        $paths = array_values(array_filter(['src', 'database'], static fn (string $dir): bool => is_dir($root.'/'.$dir)));

        $process = new Process([PHP_BINARY, $pint, '--test', '--format=json', ...$paths], $root, timeout: 300);
        $process->run();

        if ($process->isSuccessful()) {
            $this->item('pint', 'pass', 'clean ('.implode(', ', $paths).')');

            return;
        }

        $report = json_decode($process->getOutput(), true);
        $files = is_array($report['files'] ?? null) ? $report['files'] : [];
        $names = array_map(static fn (array $file): string => (string) ($file['path'] ?? $file['name'] ?? '?'), $files);

        $this->item(
            'pint',
            'fail',
            $names === [] ? 'pint --test failed' : \sprintf('%d file%s formatting: %s', count($names), count($names) === 1 ? ' needs' : 's need', implode(', ', $names)),
            'Run `vendor/bin/pint '.implode(' ', $paths).'`',
        );
    }

    protected function autoload(): void
    {
        $problems = (new AutoloadAudit($this->package()))->run();

        if ($problems === []) {
            $this->item('autoload', 'pass', 'every class under the package paths resolves');

            return;
        }

        $this->item('autoload', 'fail', implode('; ', $problems), 'Map the namespace in composer.json autoload / autoload-dev psr-4 and run composer dump-autoload');
    }

    /**
     * @param  'pass'|'fail'|'skip'  $status
     */
    protected function item(string $key, string $status, string $detail, ?string $help = null): void
    {
        $this->items[] = ['key' => $key, 'status' => $status, 'detail' => $detail, 'help' => $status === 'fail' ? $help : null];
    }

    protected function render(): void
    {
        if (AgentMode::enabled()) {
            foreach ($this->items as $item) {
                $this->line(\sprintf('%s: %s — %s', $item['key'], $item['status'], $item['detail']));
            }

            $help = array_values(array_filter(array_column($this->items, 'help')));

            if ($help !== []) {
                $this->line(\sprintf('help[%d]:', count($help)));

                foreach ($help as $line) {
                    $this->line('  '.$line);
                }
            }

            return;
        }

        foreach ($this->items as $item) {
            $tag = match ($item['status']) {
                'pass' => '<info>PASS</info>',
                'fail' => '<error>FAIL</error>',
                'skip' => '<comment>SKIP</comment>',
            };

            $this->components->twoColumnDetail($item['key'], $tag.' '.$item['detail']);

            if ($item['help'] !== null) {
                $this->line('    '.$item['help']);
            }
        }
    }
}
