<?php

namespace Packstub\Partisan\Console;

use Orchestra\Canvas\Console\FactoryMakeCommand as CanvasFactoryMakeCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:factory', description: 'Create a new model factory')]
class FactoryMakeCommand extends CanvasFactoryMakeCommand
{
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
