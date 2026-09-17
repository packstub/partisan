<?php

namespace Packstub\Partisan\Console;

use Orchestra\Canvas\Console\MigrateMakeCommand as CanvasMigrateMakeCommand;
use Packstub\Partisan\Agent\AgentMode;
use Packstub\Partisan\Console\Concerns\UsesFieldSpec;
use Packstub\Partisan\Support\Fields\Field;
use Packstub\Partisan\Support\Fields\RelatedModel;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:migration', description: 'Create a new migration file')]
class MigrateMakeCommand extends CanvasMigrateMakeCommand
{
    use UsesFieldSpec;

    protected function configure(): void
    {
        parent::configure();

        $this->addFieldsOption();
    }

    /**
     * With --fields, the column lines go into the Schema::create / Schema::table
     * closure the stub wrote, before `$table->timestamps();` when there is one.
     */
    public function handle()
    {
        $fields = $this->fields();

        if ($fields !== []) {
            $this->creator->afterCreate(function (string $table, string $path) use ($fields): void {
                $this->writeColumns($path, $fields);
            });
        }

        return parent::handle();
    }

    /**
     * @param  list<Field>  $fields
     */
    protected function writeColumns(string $path, array $fields): void
    {
        $code = (string) file_get_contents($path);
        $lines = explode("\n", $code);

        $anchor = null;
        $indent = null;

        foreach ($lines as $index => $line) {
            if (preg_match('/^(\s*)\$table->(?:timestamps|nullableTimestamps|timestampsTz)\(\);/', $line, $matches) === 1) {
                [$anchor, $indent] = [$index, $matches[1]];

                break;
            }
        }

        if ($anchor === null) {
            $open = null;

            foreach ($lines as $index => $line) {
                if ($open === null && preg_match('/Schema::(?:create|table)\(/', $line) === 1) {
                    $open = $index;

                    continue;
                }

                if ($open !== null && preg_match('/^(\s*)\}\);/', $line, $matches) === 1) {
                    [$anchor, $indent] = [$index, $matches[1].'    '];

                    break;
                }
            }
        }

        if ($anchor === null) {
            $this->components->warn('--fields ignored: the migration has no Schema::create or Schema::table block to add columns to.');

            return;
        }

        $modelNamespace = $this->generatorPreset()->modelNamespace();

        $columns = array_map(function (Field $field) use ($indent, $modelNamespace): string {
            $related = $field->isForeignKey() ? RelatedModel::resolve((string) $field->relatedModelName(), $modelNamespace) : null;

            // A constrained() with no table infers it from the column; only an
            // explicitly named model (foreignIdFor) needs the table spelled out.
            $table = $related !== null && str_ends_with($field->type, 'For') ? $related->table() : null;

            return $indent.$field->column($table);
        }, $fields);

        // The stub's `//` placeholder inside an empty Schema::table block.
        if ($anchor > 0 && trim($lines[$anchor - 1]) === '//') {
            array_splice($lines, $anchor - 1, 1);
            $anchor--;
        }

        array_splice($lines, $anchor, 0, $columns);

        file_put_contents($path, implode("\n", $lines));

        if (AgentMode::enabled()) {
            $this->output->writeln(\sprintf('columns[%d]: written from --fields', count($columns)));
        }
    }
}
