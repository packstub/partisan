<?php

namespace Packstub\Partisan\Support;

use FilesystemIterator;
use Packstub\Partisan\PackageContext;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * Every class-like file under the package's psr-4 paths must resolve to the
 * class its path implies, and files outside those paths (a database/factories
 * directory nobody mapped) must not declare classes that cannot be loaded.
 */
final class AutoloadAudit
{
    public function __construct(private readonly PackageContext $package) {}

    /**
     * @return list<string> human-readable problems, empty when everything resolves
     */
    public function run(): array
    {
        $problems = [];
        $covered = [];

        foreach ($this->package->autoload as $prefix => $paths) {
            foreach ($paths as $base) {
                if (! is_dir($base) || ! $this->insidePackage($base)) {
                    continue;
                }

                $covered[] = rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

                foreach ($this->phpFiles($base) as $file) {
                    $declared = $this->declaredClass($file);

                    if ($declared === null) {
                        continue;
                    }

                    $relative = substr($file, strlen(rtrim($base, DIRECTORY_SEPARATOR)) + 1);
                    $expected = $prefix.str_replace('/', '\\', substr($relative, 0, -4));

                    if ($declared !== $expected) {
                        $problems[] = \sprintf('%s declares %s but its path maps to %s', $this->relative($file), $declared, $expected);
                    } elseif (! $this->resolves($declared)) {
                        $problems[] = \sprintf('%s cannot be loaded (run composer dump-autoload)', $declared);
                    }
                }
            }
        }

        foreach (['database/factories', 'database/seeders'] as $dir) {
            $base = $this->package->rootPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $dir);

            if (! is_dir($base)) {
                continue;
            }

            foreach ($this->phpFiles($base) as $file) {
                foreach ($covered as $coveredBase) {
                    if (str_starts_with($file, $coveredBase)) {
                        continue 2;
                    }
                }

                $declared = $this->declaredClass($file);

                if ($declared !== null && ! $this->resolves($declared)) {
                    $problems[] = \sprintf('%s is not covered by composer.json autoload (%s)', $declared, $this->relative($file));
                }
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $base): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS));

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Fully qualified name of the class, interface, trait or enum a file
     * declares, or null for files without one (migrations, helpers, config).
     */
    private function declaredClass(string $file): ?string
    {
        $source = (string) file_get_contents($file);

        if (preg_match('/^\s*namespace\s+([^;\s]+)\s*;/m', $source, $namespace) !== 1) {
            return null;
        }

        if (preg_match('/^\s*(?:abstract\s+|final\s+|readonly\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $source, $class) !== 1) {
            return null;
        }

        return $namespace[1].'\\'.$class[1];
    }

    private function resolves(string $class): bool
    {
        try {
            return class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class);
        } catch (Throwable) {
            return false;
        }
    }

    private function insidePackage(string $path): bool
    {
        $root = $this->package->rootPath.DIRECTORY_SEPARATOR;

        return str_starts_with($path.DIRECTORY_SEPARATOR, $root) && ! str_starts_with($path, $root.'vendor');
    }

    private function relative(string $file): string
    {
        return substr($file, strlen($this->package->rootPath) + 1);
    }
}
