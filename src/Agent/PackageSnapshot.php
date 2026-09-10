<?php

namespace Packstub\Partisan\Agent;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Fingerprint of every file in the package, taken before a command runs and
 * diffed afterwards to report what the command wrote.
 */
final class PackageSnapshot
{
    private const SKIP = ['vendor', 'node_modules', '.git', '.idea', '.vscode', 'storage', 'bootstrap', '.phpunit.cache'];

    private const HASH_LIMIT = 262_144;

    /**
     * @param  array<string, string>  $files  relative path => fingerprint
     */
    private function __construct(
        public readonly string $rootPath,
        public readonly array $files,
    ) {}

    public static function take(string $rootPath): self
    {
        $rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $files = [];

        if (! is_dir($rootPath)) {
            return new self($rootPath, $files);
        }

        $directory = new RecursiveDirectoryIterator($rootPath, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS);

        $filtered = new RecursiveCallbackFilterIterator($directory, static function (SplFileInfo $file): bool {
            if ($file->isLink()) {
                return false;
            }

            return ! ($file->isDir() && in_array($file->getFilename(), self::SKIP, true));
        });

        foreach (new RecursiveIteratorIterator($filtered) as $file) {
            /** @var SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }

            $files[substr($file->getPathname(), strlen($rootPath) + 1)] = self::fingerprint($file);
        }

        return new self($rootPath, $files);
    }

    /**
     * Files added or modified since this snapshot was taken.
     *
     * @return array{created: list<string>, updated: list<string>}
     */
    public function changes(): array
    {
        $now = self::take($this->rootPath);
        $created = [];
        $updated = [];

        foreach ($now->files as $path => $fingerprint) {
            if (! array_key_exists($path, $this->files)) {
                $created[] = $path;
            } elseif ($this->files[$path] !== $fingerprint) {
                $updated[] = $path;
            }
        }

        sort($created);
        sort($updated);

        return ['created' => $created, 'updated' => $updated];
    }

    private static function fingerprint(SplFileInfo $file): string
    {
        $size = $file->getSize();

        if ($size <= self::HASH_LIMIT) {
            return (string) md5_file($file->getPathname());
        }

        return $file->getMTime().':'.$size;
    }
}
