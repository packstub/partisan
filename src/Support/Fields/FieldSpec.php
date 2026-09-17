<?php

namespace Packstub\Partisan\Support\Fields;

use Symfony\Component\Console\Exception\InvalidArgumentException;

/**
 * Parses `--fields=name:type[(args)][:modifier…][=default],…`. Parentheses
 * keep their Blueprint meaning (column arguments), modifiers are bare words
 * and `=` sets the default, so `capacity:unsignedInteger=10`,
 * `price:decimal(8,2)=0`, `status:enum(draft,sent)=draft`, `notes:text:nullable`.
 */
final class FieldSpec
{
    /**
     * @return list<Field>
     *
     * @throws InvalidArgumentException when a field cannot be read; the message says which and why
     */
    public static function parse(string $spec): array
    {
        $items = array_values(array_filter(array_map('trim', self::splitOutsideParentheses($spec, ',')), static fn (string $item): bool => $item !== ''));

        if ($items === []) {
            throw new InvalidArgumentException('--fields is empty; expected name:type[(args)][:modifier][=default], e.g. --fields=name:string,capacity:unsignedInteger=10,notes:text:nullable');
        }

        return array_map(self::parseField(...), $items);
    }

    private static function parseField(string $item): Field
    {
        $default = null;
        $equals = self::positionOutsideParentheses($item, '=');

        if ($equals !== null) {
            $default = trim(substr($item, $equals + 1));
            $item = substr($item, 0, $equals);
        }

        $parts = array_map('trim', self::splitOutsideParentheses($item, ':'));
        $name = array_shift($parts) ?? '';

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException(\sprintf('Field "%s" in --fields has no valid column name; expected name:type, e.g. capacity:unsignedInteger', $item));
        }

        $typeWithArgs = array_shift($parts) ?? '';

        if (preg_match('/^(\w+)(?:\((.*)\))?$/', $typeWithArgs, $matches) !== 1 || $matches[1] === '') {
            throw new InvalidArgumentException(\sprintf('Field "%s" in --fields has no type; expected name:type, e.g. %s:string', $name, $name));
        }

        $type = $matches[1];
        $args = isset($matches[2]) && trim($matches[2]) !== '' ? array_values(array_map('trim', explode(',', $matches[2]))) : [];

        if (! \in_array($type, self::types(), true)) {
            throw new InvalidArgumentException(\sprintf('Unknown column type "%s" for field "%s" in --fields; types are Blueprint column methods: string, text, integer, unsignedInteger, bigInteger, decimal(8,2), float, boolean, date, dateTime, timestamp, json, uuid, ulid, enum(a,b), foreignId, foreignIdFor(Model), morphs, …', $type, $name));
        }

        $foreign = \in_array($type, Field::FOREIGN_TYPES, true);

        foreach ($parts as $modifier) {
            if (\in_array($modifier, Field::MODIFIERS, true)) {
                continue;
            }

            if (\in_array($modifier, Field::FOREIGN_MODIFIERS, true)) {
                if ($foreign) {
                    continue;
                }

                throw new InvalidArgumentException(\sprintf('Modifier "%s" on field "%s" in --fields only applies to foreignId columns', $modifier, $name));
            }

            throw new InvalidArgumentException(\sprintf('Unknown modifier "%s" for field "%s" in --fields; modifiers are %s and, on foreign keys, %s; a default is written as %s:%s=<value>', $modifier, $name, implode(', ', Field::MODIFIERS), implode(', ', Field::FOREIGN_MODIFIERS), $name, $type));
        }

        if (str_ends_with($type, 'For') && $args === []) {
            throw new InvalidArgumentException(\sprintf('Field "%s" in --fields: %s needs the related model, e.g. %s:%s(User)', $name, $type, $name, $type));
        }

        return new Field($name, $type, $args, array_values($parts), $default);
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [...Field::SCALAR_TYPES, ...Field::INTEGER_TYPES, ...Field::FOREIGN_TYPES, ...Field::MORPH_TYPES, ...Field::BARE_TYPES];
    }

    /**
     * @return list<string>
     */
    private static function splitOutsideParentheses(string $value, string $separator): array
    {
        $parts = [];
        $current = '';
        $depth = 0;

        foreach (str_split($value) as $char) {
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            }

            if ($char === $separator && $depth === 0) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        $parts[] = $current;

        return $parts;
    }

    private static function positionOutsideParentheses(string $value, string $needle): ?int
    {
        $depth = 0;

        foreach (str_split($value) as $index => $char) {
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($char === $needle && $depth === 0) {
                return $index;
            }
        }

        return null;
    }
}
