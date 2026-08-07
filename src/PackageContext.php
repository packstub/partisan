<?php

namespace Packstub\Partisan;

use Closure;
use Illuminate\Foundation\Application;
use RuntimeException;

/**
 * Resolved identity of the package under development: where its source and
 * tests live and which namespaces they use, read from the package's own
 * composer.json in the working directory.
 */
final class PackageContext
{
    /**
     * @param  array<int, class-string>  $providers
     * @param  array<string, array<int, string>>  $autoload  psr-4 prefix => absolute paths, from autoload + autoload-dev
     */
    public function __construct(
        public readonly string $rootPath,
        public readonly string $namespace,
        public readonly string $sourcePath,
        public readonly string $testsNamespace,
        public readonly string $testsPath,
        public readonly array $providers = [],
        public readonly array $autoload = [],
    ) {}

    public static function fromComposer(string $workingPath): self
    {
        $composerFile = rtrim($workingPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'composer.json';

        if (! is_file($composerFile)) {
            throw new RuntimeException(\sprintf(
                'Unable to find a composer.json in [%s]. Run partisan from your package root directory.', $workingPath
            ));
        }

        $composer = json_decode((string) file_get_contents($composerFile), true);

        [$namespace, $sourcePath] = self::primaryAutoload(
            $composer['autoload']['psr-4'] ?? [],
            'Unable to detect the package namespace: composer.json has no "autoload.psr-4" entry.'
        );

        try {
            [$testsNamespace, $testsPath] = self::primaryAutoload($composer['autoload-dev']['psr-4'] ?? [], '', 'tests');
        } catch (RuntimeException) {
            [$testsNamespace, $testsPath] = [$namespace.'Tests\\', 'tests'];
        }

        $rootPath = rtrim($workingPath, DIRECTORY_SEPARATOR);

        $autoload = [];

        foreach ([$composer['autoload']['psr-4'] ?? [], $composer['autoload-dev']['psr-4'] ?? []] as $psr4) {
            foreach ($psr4 as $prefix => $paths) {
                foreach ((array) $paths as $path) {
                    $autoload[$prefix][] = $rootPath.DIRECTORY_SEPARATOR.trim($path, '/');
                }
            }
        }

        return new self(
            rootPath: $rootPath,
            namespace: $namespace,
            sourcePath: $rootPath.DIRECTORY_SEPARATOR.trim($sourcePath, '/'),
            testsNamespace: $testsNamespace,
            testsPath: $rootPath.DIRECTORY_SEPARATOR.trim($testsPath, '/'),
            providers: (array) ($composer['extra']['laravel']['providers'] ?? []),
            autoload: $autoload,
        );
    }

    /**
     * Pick the psr-4 entry to treat as the package root — the one mapped to
     * the conventional directory when present, otherwise the first entry.
     *
     * @param  array<string, string|array<int, string>>  $psr4
     * @return array{0: string, 1: string}
     */
    private static function primaryAutoload(array $psr4, string $error, string $conventional = 'src'): array
    {
        if ($psr4 === []) {
            throw new RuntimeException($error);
        }

        foreach ($psr4 as $namespace => $path) {
            foreach ((array) $path as $candidate) {
                if (trim($candidate, '/') === $conventional) {
                    return [$namespace, $candidate];
                }
            }
        }

        $namespace = array_key_first($psr4);
        $path = (array) $psr4[$namespace];

        return [$namespace, (string) reset($path)];
    }

    /**
     * Point the booted skeleton application's generator-facing paths and root
     * namespace at the package, so every make: command targets src/.
     */
    public function applyTo(Application $app): void
    {
        $app->useAppPath($this->sourcePath);
        $app->useDatabasePath($this->rootPath.DIRECTORY_SEPARATOR.'database');

        // Application::getNamespace() lazily parses composer.json at the
        // skeleton's base path; pre-filling its cache is the supported-shape
        // equivalent of overriding it.
        Closure::bind(function (Application $app, string $namespace): void {
            $app->namespace = $namespace;
        }, null, Application::class)($app, $this->namespace);

        $app->instance(self::class, $this);
    }
}
