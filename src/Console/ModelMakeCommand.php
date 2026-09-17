<?php

namespace Packstub\Partisan\Console;

use Illuminate\Support\Str;
use Orchestra\Canvas\Console\ModelMakeCommand as CanvasModelMakeCommand;
use Packstub\Partisan\Agent\AgentMode;
use Packstub\Partisan\Console\Concerns\UsesFieldSpec;
use Packstub\Partisan\Support\Fields\Field;
use Packstub\Partisan\Support\Fields\RelatedModel;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:model', description: 'Create a new Eloquent model class')]
class ModelMakeCommand extends CanvasModelMakeCommand
{
    use UsesFieldSpec;

    /** @var list<RelatedModel> */
    private array $unresolved = [];

    protected function configure(): void
    {
        parent::configure();

        $this->addFieldsOption();
    }

    /**
     * Wire generated models to their package factory. Deliberately
     * shape-agnostic: whatever form the upstream stub uses to reference the
     * factory (docblock generic, #[UseFactory] attribute, import), the
     * reference is rewritten in place; the attribute is only added when the
     * generated code carries no factory resolution mechanism at all.
     */
    public function generatingCode(string $stub, string $className): string
    {
        $stub = $this->withFields(parent::generatingCode($stub, $className));

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

    /**
     * --fields: $fillable for every column, casts() for the types that need
     * one, and a belongsTo / morphTo per foreign key or morph column.
     */
    protected function withFields(string $stub): string
    {
        $fields = $this->fields();

        if ($fields === []) {
            return $stub;
        }

        $namespace = rtrim($this->generatorPreset()->modelNamespace(), '\\');
        $imports = [];
        $members = [];

        $fillable = array_merge(...array_map(static fn (Field $field): array => $field->fillable(), $fields));

        if ($fillable !== []) {
            $members[] = self::fillableMember($fillable);
        }

        $casts = [];

        foreach ($fields as $field) {
            if ($field->cast() !== null) {
                $casts[$field->name] = $field->cast();
            }
        }

        if ($casts !== []) {
            $members[] = self::castsMember($casts);
        }

        foreach ($fields as $field) {
            if ($field->isMorph()) {
                $imports[] = 'Illuminate\\Database\\Eloquent\\Relations\\MorphTo';
                $members[] = self::relationMember((string) $field->relationName(), 'MorphTo', '$this->morphTo()');

                continue;
            }

            if (! $field->isForeignKey()) {
                continue;
            }

            $related = RelatedModel::resolve((string) $field->relatedModelName(), $namespace);

            if ($related->unresolved) {
                $this->unresolved[] = $related;
            }

            $imports[] = 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo';
            $imports[] = $related->importFor($namespace);
            $members[] = self::relationMember((string) $field->relationName(), 'BelongsTo', '$this->belongsTo('.$related->relationArgument().')');
        }

        return $this->withImports($this->withClassMembers($stub, implode("\n\n", $members)), $imports);
    }

    /**
     * @param  list<string>  $columns
     */
    private static function fillableMember(array $columns): string
    {
        $lines = implode("\n", array_map(static fn (string $column): string => '        '.Field::quote($column).',', $columns));

        return <<<PHP
            /**
             * The attributes that are mass assignable.
             *
             * @var list<string>
             */
            protected \$fillable = [
        {$lines}
            ];
        PHP;
    }

    /**
     * @param  array<string, string>  $casts
     */
    private static function castsMember(array $casts): string
    {
        $lines = [];

        foreach ($casts as $column => $cast) {
            $lines[] = '            '.Field::quote($column).' => '.Field::quote($cast).',';
        }

        $lines = implode("\n", $lines);

        return <<<PHP
            /**
             * Get the attributes that should be cast.
             *
             * @return array<string, string>
             */
            protected function casts(): array
            {
                return [
        {$lines}
                ];
            }
        PHP;
    }

    private static function relationMember(string $name, string $type, string $body): string
    {
        return <<<PHP
            public function {$name}(): {$type}
            {
                return {$body};
            }
        PHP;
    }

    /**
     * The spec flows down to the migration and the factory; afterwards the
     * relations that could not be resolved in the package are reported so
     * the agent points them at the host model or a config key.
     */
    protected function afterCodeHasBeenGenerated(): void
    {
        parent::afterCodeHasBeenGenerated();

        if ($this->unresolved === []) {
            return;
        }

        $model = class_basename($this->qualifyClass($this->getNameInput()));

        if (AgentMode::enabled()) {
            // The raw output: the styled one drops a line's leading whitespace.
            $output = $this->output->getOutput();
            $output->writeln(\sprintf('unresolved[%d]:', count($this->unresolved)));

            foreach ($this->unresolved as $related) {
                $output->writeln(\sprintf('  - %s (relation %s() on %s): no such class in the package; point it at the host app model or a config key', $related->class, Str::camel($related->name), $model));
            }

            return;
        }

        foreach ($this->unresolved as $related) {
            $this->components->warn(\sprintf('%s does not exist yet: relation %s() on %s points at it; change it to the host app model or a config key if that is where it lives.', $related->class, Str::camel($related->name), $model));
        }
    }

    protected function createFactory()
    {
        $factory = Str::studly($this->getNameInput());

        $this->call('make:factory', array_filter([
            'name' => "{$factory}Factory",
            '--model' => $this->qualifyClass($this->getNameInput()),
            '--preset' => $this->option('preset'),
            '--fields' => $this->option('fields'),
        ]));
    }

    protected function createMigration()
    {
        $table = Str::snake(Str::pluralStudly(class_basename($this->getNameInput())));

        if ($this->option('pivot')) {
            $table = Str::singular($table);
        }

        $this->call('make:migration', array_filter([
            'name' => "create_{$table}_table",
            '--create' => $table,
            '--fullpath' => true,
            '--preset' => $this->option('preset'),
            '--fields' => $this->option('fields'),
        ]));
    }
}
