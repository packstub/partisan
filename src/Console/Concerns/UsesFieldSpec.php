<?php

namespace Packstub\Partisan\Console\Concerns;

use Packstub\Partisan\Support\Fields\Field;
use Packstub\Partisan\Support\Fields\FieldSpec;
use Symfony\Component\Console\Input\InputOption;

/**
 * The `--fields` option shared by make:model, make:migration and make:factory,
 * plus the two edits every spec-carrying generator makes to generated code:
 * adding imports and adding members to a class body.
 */
trait UsesFieldSpec
{
    /** @var list<Field>|null */
    private ?array $parsedFields = null;

    protected function addFieldsOption(): void
    {
        $this->getDefinition()->addOption(new InputOption(
            'fields',
            null,
            InputOption::VALUE_REQUIRED,
            'Columns as name:type[(args)][:modifier][=default], e.g. name:string,capacity:unsignedInteger=10,price:decimal(8,2)=0,notes:text:nullable,user_id:foreignId',
        ));
    }

    /**
     * @return list<Field>
     */
    protected function fields(): array
    {
        if ($this->parsedFields !== null) {
            return $this->parsedFields;
        }

        $spec = $this->option('fields');

        return $this->parsedFields = \is_string($spec) && trim($spec) !== '' ? FieldSpec::parse($spec) : [];
    }

    /**
     * Add top-level imports after the last existing one, skipping duplicates.
     *
     * @param  list<string>  $classes
     */
    protected function withImports(string $code, array $classes): string
    {
        $classes = array_values(array_unique(array_filter($classes)));

        $lines = array_values(array_filter(
            array_map(static fn (string $class): string => 'use '.ltrim($class, '\\').';', $classes),
            static fn (string $line): bool => ! str_contains($code, $line."\n"),
        ));

        if ($lines === []) {
            return $code;
        }

        if (preg_match_all('/^use [^;]+;$/m', $code, $matches, PREG_OFFSET_CAPTURE) > 0) {
            [$import, $offset] = end($matches[0]);

            return substr_replace($code, $import."\n".implode("\n", $lines), $offset, \strlen($import));
        }

        if (preg_match('/^namespace [^;]+;\n/m', $code, $matches, PREG_OFFSET_CAPTURE) === 1) {
            [$namespace, $offset] = $matches[0];

            return substr_replace($code, $namespace."\n".implode("\n", $lines)."\n", $offset, \strlen($namespace));
        }

        return $code;
    }

    /**
     * Append members before the closing brace of the (last) class in the file.
     */
    protected function withClassMembers(string $code, string $members): string
    {
        $members = rtrim($members);

        if ($members === '') {
            return $code;
        }

        $close = strrpos($code, '}');

        if ($close === false) {
            return $code;
        }

        $before = rtrim(substr($code, 0, $close));
        $after = substr($code, $close);

        // A class whose body is empty or ends in `{` needs no separating blank line.
        $separator = str_ends_with($before, '{') ? "\n" : "\n\n";

        return $before.$separator.$members."\n".$after;
    }
}
