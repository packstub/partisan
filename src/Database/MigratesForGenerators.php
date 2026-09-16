<?php

namespace Packstub\Partisan\Database;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Foundation\Application;
use Packstub\Partisan\Agent\AgentMode;
use Packstub\Partisan\PackageContext;

/**
 * Filament's `--generate` builds the form and table from the model's table.
 * A package has no database to read from, so before such a generator runs
 * the package's migrations are applied to an in-memory SQLite connection.
 */
final class MigratesForGenerators
{
    public function __construct(
        private readonly Application $app,
        private readonly PackageContext $package,
    ) {}

    public function starting(CommandStarting $event): void
    {
        if (! str_starts_with((string) $event->command, 'make:filament-')) {
            return;
        }

        if (! $event->input->hasParameterOption(['--generate', '-G'], true)) {
            return;
        }

        $result = (new PackageDatabase($this->app, $this->package))->migrate();
        $agent = AgentMode::enabled();

        if ($result['error'] !== null) {
            $event->output->writeln($agent
                ? 'migrated[0]: could not read the package schema — '.$result['error']
                : '<comment>Could not apply the package migrations for --generate: '.$result['error'].'</comment>');

            return;
        }

        $event->output->writeln($agent
            ? \sprintf('migrated[%d]: package migrations applied to an in-memory sqlite so --generate can read the schema', $result['migrated'])
            : \sprintf('<info>Applied %d package migration%s to an in-memory database so --generate can read the schema.</info>', $result['migrated'], $result['migrated'] === 1 ? '' : 's'));
    }
}
