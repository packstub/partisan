<?php

namespace Packstub\Partisan\Console;

use Illuminate\Support\Str;
use Orchestra\Canvas\Console\FactoryMakeCommand as CanvasFactoryMakeCommand;
use Packstub\Partisan\Console\Concerns\UsesFieldSpec;
use Packstub\Partisan\Support\Fields\Field;
use Packstub\Partisan\Support\Fields\RelatedModel;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:factory', description: 'Create a new model factory')]
class FactoryMakeCommand extends CanvasFactoryMakeCommand
{
    use UsesFieldSpec;

    protected function configure(): void
    {
        parent::configure();

        $this->addFieldsOption();
    }

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

        $stub = implode("\n", array_filter(explode("\n", $stub), static function (string $line) use (&$seen): bool {
            if (preg_match('/^use [^;]+;$/', trim($line)) === 1) {
                if (\in_array($line, $seen, true)) {
                    return false;
                }

                $seen[] = $line;
            }

            return true;
        }));

        return $this->withFields($stub);
    }

    /**
     * --fields: one line per column in definition(), a fake by type (or by name
     * when the name says what it is), a related factory for foreign keys.
     */
    protected function withFields(string $stub): string
    {
        $fields = $this->fields();

        if ($fields === []) {
            return $stub;
        }

        $model = $this->option('model');
        $modelNamespace = \is_string($model) && $model !== ''
            ? Str::beforeLast($this->qualifyModel($model), '\\')
            : rtrim($this->generatorPreset()->modelNamespace(), '\\');
        $factoryNamespace = rtrim($this->generatorPreset()->factoryNamespace(), '\\');

        $imports = [];
        $lines = [];

        foreach ($fields as $field) {
            if ($field->isForeignKey()) {
                $related = RelatedModel::resolve((string) $field->relatedModelName(), $modelNamespace);
                $imports[] = $related->importFor($factoryNamespace);
                $lines[] = '            '.Field::quote($field->name).' => '.$related->factoryValue().',';

                continue;
            }

            $fake = $field->fake();

            if ($fake === null) {
                continue;
            }

            if (str_contains($fake, 'Str::')) {
                $imports[] = 'Illuminate\\Support\\Str';
            }

            $lines[] = '            '.Field::quote($field->name).' => '.$fake.',';
        }

        if ($lines === []) {
            return $stub;
        }

        $stub = (string) preg_replace('/^\s*\/\/\s*$/m', implode("\n", $lines), $stub, 1);

        return $this->withImports($stub, $imports);
    }
}
