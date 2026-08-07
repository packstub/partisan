<?php

namespace Packstub\Partisan\Support;

use Packstub\Partisan\PackageContext;

/**
 * Registers a generated artisan command class in the package's service
 * provider, editing the file surgically so existing formatting survives.
 */
final class ProviderCommandRegistrar
{
    public function __construct(private readonly PackageContext $package) {}

    /**
     * Pick the provider to register commands in: the first declared provider
     * whose file lives in the package, is not a Filament panel provider, and
     * (preferably) already has a command registration block.
     *
     * @return array{provider: class-string, file: string}|null
     */
    public function locate(): ?array
    {
        $candidates = [];

        foreach ($this->package->providers as $provider) {
            $file = $this->fileFor($provider);

            if ($file === null || str_contains((string) file_get_contents($file), 'PanelProvider')) {
                continue;
            }

            $candidates[] = ['provider' => $provider, 'file' => $file];
        }

        foreach ($candidates as $candidate) {
            if (preg_match($this->blockPattern(), (string) file_get_contents($candidate['file'])) === 1) {
                return $candidate;
            }
        }

        return $candidates[0] ?? null;
    }

    /**
     * @return 'registered'|'already'|'no-block'
     */
    public function register(string $providerFile, string $commandClass): string
    {
        $content = (string) file_get_contents($providerFile);
        $short = class_basename($commandClass);

        if (preg_match('/\b'.preg_quote($short, '/').'::class/', $content) === 1) {
            return 'already';
        }

        $updated = $this->insertIntoBlock($content, $short) ?? $this->insertIntoBoot($content, $short);

        if ($updated === null) {
            return 'no-block';
        }

        file_put_contents($providerFile, $this->ensureImport($updated, $commandClass));

        return 'registered';
    }

    public function snippet(string $commandClass): string
    {
        return '$this->commands(['.class_basename($commandClass).'::class]);';
    }

    /**
     * Resolve a class to its file through the package's psr-4 maps — no
     * autoloading, so providers with unloadable parents are still editable.
     */
    private function fileFor(string $class): ?string
    {
        foreach ($this->package->autoload as $prefix => $paths) {
            if (! str_starts_with($class, $prefix)) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($class, \strlen($prefix))).'.php';

            foreach ($paths as $base) {
                if (is_file($base.'/'.$relative)) {
                    return $base.'/'.$relative;
                }
            }
        }

        return null;
    }

    private function blockPattern(): string
    {
        return '/(\$this->commands\(\[|->hasCommands\(\[)/';
    }

    /**
     * Insert into an existing $this->commands([...]) / ->hasCommands([...])
     * block, keeping the block's indentation.
     */
    private function insertIntoBlock(string $content, string $short): ?string
    {
        if (preg_match($this->blockPattern(), $content, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $openBracket = $match[0][1] + \strlen($match[0][0]);

        $lineStart = strrpos(substr($content, 0, $openBracket), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        preg_match('/^[ \t]*/', substr($content, $lineStart), $indentMatch);
        $indent = $indentMatch[0];
        $inner = $indent.'    ';

        // Empty inline array — rewrite "[]" as a multi-line block.
        if (preg_match('/\G[ \t]*\]/', $content, $emptyMatch, 0, $openBracket) === 1) {
            return substr_replace(
                $content,
                "\n{$inner}{$short}::class,\n{$indent}]",
                $openBracket,
                \strlen($emptyMatch[0]),
            );
        }

        return substr_replace($content, "\n{$inner}{$short}::class,", $openBracket, 0);
    }

    /**
     * No registration block anywhere: append one to the boot() method.
     */
    private function insertIntoBoot(string $content, string $short): ?string
    {
        if (preg_match('/^(?<indent>[ \t]*).*function\s+boot\s*\([^)]*\)(?:\s*:\s*[\w\\\\|?]+)?\s*\{/m', $content, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $indent = $match['indent'][0];
        $openBrace = $match[0][1] + \strlen($match[0][0]) - 1;
        $closeBrace = $this->matchingBrace($content, $openBrace);

        if ($closeBrace === null) {
            return null;
        }

        $body = trim(substr($content, $openBrace + 1, $closeBrace - $openBrace - 1));
        $block = ($body === '' ? '' : "\n")
            ."{$indent}    \$this->commands([\n"
            ."{$indent}        {$short}::class,\n"
            ."{$indent}    ]);\n{$indent}";

        return substr_replace($content, $block, $closeBrace, 0);
    }

    /**
     * Byte offset of the brace closing the one at $openBrace, skipping
     * string literals and comments.
     */
    private function matchingBrace(string $content, int $openBrace): ?int
    {
        $depth = 0;
        $length = \strlen($content);

        for ($i = $openBrace; $i < $length; $i++) {
            $char = $content[$i];

            if ($char === "'" || $char === '"') {
                for ($i++; $i < $length && $content[$i] !== $char; $i++) {
                    if ($content[$i] === '\\') {
                        $i++;
                    }
                }

                continue;
            }

            if ($char === '/' && $i + 1 < $length && $content[$i + 1] === '/') {
                $i = strpos($content, "\n", $i) ?: $length;

                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}' && --$depth === 0) {
                return $i;
            }
        }

        return null;
    }

    private function ensureImport(string $content, string $commandClass): string
    {
        if (preg_match('/^use '.preg_quote($commandClass, '/').';$/m', $content) === 1) {
            return $content;
        }

        if (preg_match_all('/^use [^;]+;$/m', $content, $matches, PREG_OFFSET_CAPTURE) > 0) {
            [$import, $offset] = end($matches[0]);

            return substr_replace($content, $import."\nuse {$commandClass};", $offset, \strlen($import));
        }

        return (string) preg_replace(
            '/^(namespace [^;]+;\n)/m',
            "$1\nuse {$commandClass};\n",
            $content,
            1,
        );
    }
}
