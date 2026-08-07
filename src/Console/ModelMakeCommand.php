<?php

namespace Packstub\Partisan\Console;

use Illuminate\Support\Str;
use Orchestra\Canvas\Console\ModelMakeCommand as CanvasModelMakeCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:model', description: 'Create a new Eloquent model class')]
class ModelMakeCommand extends CanvasModelMakeCommand
{
    /**
     * Wire generated models to their package factory. Deliberately
     * shape-agnostic: whatever form the upstream stub uses to reference the
     * factory (docblock generic, #[UseFactory] attribute, import), the
     * reference is rewritten in place; the attribute is only added when the
     * generated code carries no factory resolution mechanism at all.
     */
    public function generatingCode(string $stub, string $className): string
    {
        $stub = parent::generatingCode($stub, $className);

        if (! str_contains($stub, 'HasFactory') && ! str_contains($stub, 'UseFactory')) {
            return $stub;
        }

        $modelPath = (string) Str::of($this->getNameInput())->studly()->replace('/', '\\');
        $factoryClass = rtrim($this->generatorPreset()->factoryNamespace(), '\\').'\\'.$modelPath.'Factory';

        $stub = $this->rewriteAppFactoryReferences($stub);

        return $this->ensureFactoryResolution($stub, $factoryClass);
    }

    /**
     * Rewrite app-convention Database\Factories\… references to the package's
     * factory namespace, wherever and however they appear. The lookbehind
     * keeps already-prefixed references untouched, so this is idempotent.
     */
    protected function rewriteAppFactoryReferences(string $stub): string
    {
        $factoryNamespace = rtrim($this->generatorPreset()->factoryNamespace(), '\\');

        return (string) preg_replace_callback(
            '/(?<![\w\\\\])\\\\?Database\\\\Factories\\\\([\w\\\\]+)/',
            static fn (array $matches): string => '\\'.$factoryNamespace.'\\'.$matches[1],
            $stub,
        );
    }

    /**
     * Guarantee the model can resolve its factory at runtime. Skipped whenever
     * the generated code already handles it — an existing #[UseFactory]
     * attribute (including one a future upstream stub may add) or a
     * newFactory() method.
     */
    protected function ensureFactoryResolution(string $stub, string $factoryClass): string
    {
        if (str_contains($stub, 'UseFactory') || str_contains($stub, 'function newFactory')) {
            return $stub;
        }

        $imports = ['use Illuminate\Database\Eloquent\Attributes\UseFactory;'];

        if (! str_contains($stub, "use {$factoryClass};")) {
            $imports[] = "use {$factoryClass};";
        }

        // Top-level imports only (^use …;) — the class-body trait use is indented.
        if (preg_match_all('/^use [^;]+;$/m', $stub, $matches, PREG_OFFSET_CAPTURE) > 0) {
            [$import, $offset] = end($matches[0]);
            $stub = substr_replace($stub, $import."\n".implode("\n", $imports), $offset, \strlen($import));
        }

        $factoryBasename = class_basename($factoryClass);

        return (string) preg_replace(
            '/^((?:final\s+|abstract\s+)?class\s)/m',
            "#[UseFactory({$factoryBasename}::class)]\n$1",
            $stub,
            1,
        );
    }
}
