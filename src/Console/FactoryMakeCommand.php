<?php

namespace Packstub\Partisan\Console;

use Illuminate\Support\Str;
use Orchestra\Canvas\Console\FactoryMakeCommand as CanvasFactoryMakeCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:factory', description: 'Create a new model factory')]
class FactoryMakeCommand extends CanvasFactoryMakeCommand
{
    /**
     * The framework qualifies --model against the package model namespace only
     * when src/Models already exists; a package's convention shouldn't depend
     * on generation order.
     */
    protected function qualifyModel(string $model)
    {
        $model = str_replace('/', '\\', ltrim($model, '\\/'));

        if (Str::startsWith($model, $this->rootNamespace())) {
            return $model;
        }

        return $this->generatorPreset()->modelNamespace().$model;
    }

    /**
     * The canvas factory stub repeats the {{ namespacedModel }} import token,
     * which leaves a duplicated use statement once both are filled in.
     */
    public function generatingCode(string $stub, string $className): string
    {
        $stub = parent::generatingCode($stub, $className);

        $seen = [];

        return implode("\n", array_filter(explode("\n", $stub), static function (string $line) use (&$seen): bool {
            if (preg_match('/^use [^;]+;$/', trim($line)) === 1) {
                if (\in_array($line, $seen, true)) {
                    return false;
                }

                $seen[] = $line;
            }

            return true;
        }));
    }
}
