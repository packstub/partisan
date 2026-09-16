<?php

namespace Packstub\Partisan\Database;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migrator;
use Packstub\Partisan\PackageContext;
use Throwable;

/**
 * A throwaway in-memory SQLite database carrying the package's migrations,
 * so generators that read the schema (`make:filament-resource --generate`)
 * and checks that run migrations have a table to look at. Nothing is written
 * to disk and no .env is needed.
 */
final class PackageDatabase
{
    public const CONNECTION = 'partisan';

    public function __construct(
        private readonly Application $app,
        private readonly PackageContext $package,
    ) {}

    /**
     * Migration directories to load: the package's own, the workbench's, and
     * whatever providers or testbench.yaml already registered with the migrator.
     *
     * @return list<string>
     */
    public function migrationPaths(): array
    {
        /** @var Migrator $migrator */
        $migrator = $this->app->make('migrator');

        $paths = [
            ...$migrator->paths(),
            $this->package->rootPath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations',
            $this->package->rootPath.DIRECTORY_SEPARATOR.'workbench'.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations',
        ];

        return array_values(array_unique(array_filter($paths, 'is_dir')));
    }

    /**
     * The skeleton application and dependencies may register migrations of
     * their own (users, cache, jobs …); only the package's are worth reporting.
     */
    private function belongsToPackage(string $file): bool
    {
        $root = $this->package->rootPath.DIRECTORY_SEPARATOR;

        return str_starts_with($file, $root) && ! str_starts_with($file, $root.'vendor'.DIRECTORY_SEPARATOR);
    }

    /**
     * Make the in-memory connection the default and run every migration into it.
     *
     * @return array{migrated: int, error: string|null}
     */
    public function migrate(): array
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $this->app->make('config');

        $config->set('database.connections.'.self::CONNECTION, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $config->set('database.default', self::CONNECTION);

        /** @var DatabaseManager $db */
        $db = $this->app->make('db');
        $db->purge(self::CONNECTION);
        $db->setDefaultConnection(self::CONNECTION);

        try {
            /** @var Migrator $migrator */
            $migrator = $this->app->make('migrator');
            $migrator->setConnection(self::CONNECTION);

            $repository = $migrator->getRepository();

            if (! $repository->repositoryExists()) {
                $repository->createRepository();
            }

            $ran = $migrator->run($this->migrationPaths());

            return ['migrated' => count(array_filter($ran, fn (string $file): bool => $this->belongsToPackage($file))), 'error' => null];
        } catch (Throwable $error) {
            return ['migrated' => 0, 'error' => $error->getMessage()];
        }
    }
}
