<?php

namespace Packstub\Partisan\Support\Fields;

use Illuminate\Support\Str;

/**
 * One column from a `--fields` spec, and the three things a generator can
 * write from it: a migration column line, a model cast and a factory value.
 * Types are Blueprint column methods, so the migration line is the spec
 * spelled out; casts and fakes follow from the type, with a few by name.
 */
final class Field
{
    /** @var list<string> */
    public const INTEGER_TYPES = ['integer', 'tinyInteger', 'smallInteger', 'mediumInteger', 'bigInteger', 'unsignedInteger', 'unsignedTinyInteger', 'unsignedSmallInteger', 'unsignedMediumInteger', 'unsignedBigInteger'];

    /** @var list<string> */
    public const FOREIGN_TYPES = ['foreignId', 'foreignIdFor', 'foreignUuid', 'foreignUlid', 'foreignUuidFor', 'foreignUlidFor'];

    /** @var list<string> */
    public const MORPH_TYPES = ['morphs', 'nullableMorphs', 'numericMorphs', 'nullableNumericMorphs', 'uuidMorphs', 'nullableUuidMorphs', 'ulidMorphs', 'nullableUlidMorphs'];

    /** Column methods that take no column name and carry no data of their own. */
    public const BARE_TYPES = ['softDeletes', 'softDeletesTz', 'softDeletesDatetime', 'rememberToken', 'timestamps', 'timestampsTz', 'nullableTimestamps'];

    /** @var list<string> */
    public const SCALAR_TYPES = ['char', 'string', 'tinyText', 'text', 'mediumText', 'longText', 'float', 'double', 'decimal', 'boolean', 'enum', 'set', 'json', 'jsonb', 'date', 'dateTime', 'dateTimeTz', 'time', 'timeTz', 'timestamp', 'timestampTz', 'year', 'binary', 'uuid', 'ulid', 'ipAddress', 'macAddress', 'geometry', 'geography', 'vector'];

    /** @var list<string> */
    public const MODIFIERS = ['nullable', 'unique', 'index', 'unsigned', 'primary', 'fullText'];

    /** @var list<string> */
    public const FOREIGN_MODIFIERS = ['unconstrained', 'cascadeOnDelete', 'nullOnDelete', 'restrictOnDelete', 'noActionOnDelete', 'cascadeOnUpdate'];

    /**
     * @param  list<string>  $args
     * @param  list<string>  $modifiers
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly array $args = [],
        public readonly array $modifiers = [],
        public readonly ?string $default = null,
    ) {}

    public function isForeignKey(): bool
    {
        return \in_array($this->type, self::FOREIGN_TYPES, true);
    }

    public function isMorph(): bool
    {
        return \in_array($this->type, self::MORPH_TYPES, true);
    }

    public function isBare(): bool
    {
        return \in_array($this->type, self::BARE_TYPES, true);
    }

    public function isNullable(): bool
    {
        return \in_array('nullable', $this->modifiers, true) || str_starts_with($this->type, 'nullable');
    }

    /**
     * The relation method a foreign key or morph column gives the model:
     * `user_id` → `user`, `commentable` → `commentable`.
     */
    public function relationName(): ?string
    {
        if ($this->isMorph()) {
            return Str::camel($this->name);
        }

        if (! $this->isForeignKey()) {
            return null;
        }

        return Str::camel((string) preg_replace('/_(id|uuid|ulid)$/', '', $this->name));
    }

    /**
     * The related model as named in the spec — the argument of `foreignIdFor(User)`,
     * otherwise inferred from the column (`author_id` → `Author`).
     */
    public function relatedModelName(): ?string
    {
        if (! $this->isForeignKey()) {
            return null;
        }

        if (str_ends_with($this->type, 'For') && isset($this->args[0]) && $this->args[0] !== '') {
            return $this->args[0];
        }

        return Str::studly((string) $this->relationName());
    }

    /**
     * Columns the model can mass-assign: a morph contributes its two columns,
     * bare types (soft deletes, remember token) none.
     *
     * @return list<string>
     */
    public function fillable(): array
    {
        if ($this->isBare()) {
            return [];
        }

        if ($this->isMorph()) {
            return [$this->name.'_type', $this->name.'_id'];
        }

        return [$this->name];
    }

    public function cast(): ?string
    {
        if ($this->type === 'decimal') {
            return 'decimal:'.($this->args[1] ?? '2');
        }

        return match (true) {
            $this->type === 'boolean' => 'boolean',
            \in_array($this->type, self::INTEGER_TYPES, true) => 'integer',
            \in_array($this->type, ['float', 'double'], true) => 'float',
            $this->type === 'date' => 'date',
            \in_array($this->type, ['dateTime', 'dateTimeTz', 'timestamp', 'timestampTz'], true) => 'datetime',
            \in_array($this->type, ['json', 'jsonb'], true) => 'array',
            default => null,
        };
    }

    /**
     * The `$table->…;` line for the migration. Foreign keys are always written
     * as `foreignId('col')->constrained('table')` so the migration never needs
     * the related class to exist; `foreignIdFor(Model)` only names the table.
     */
    public function column(?string $relatedTable = null): string
    {
        if ($this->isBare()) {
            return \sprintf('$table->%s();', $this->type);
        }

        if ($this->isMorph()) {
            return \sprintf('$table->%s(%s);', $this->type, self::quote($this->name));
        }

        if ($this->isForeignKey()) {
            $method = match (true) {
                str_starts_with($this->type, 'foreignUuid') => 'foreignUuid',
                str_starts_with($this->type, 'foreignUlid') => 'foreignUlid',
                default => 'foreignId',
            };

            $line = \sprintf('$table->%s(%s)', $method, self::quote($this->name));

            if ($this->isNullable()) {
                $line .= '->nullable()';
            }

            if ($this->default !== null) {
                $line .= '->default('.self::literal($this->default).')';
            }

            foreach (['unique', 'index'] as $modifier) {
                if (\in_array($modifier, $this->modifiers, true)) {
                    $line .= '->'.$modifier.'()';
                }
            }

            if (! \in_array('unconstrained', $this->modifiers, true)) {
                $line .= $relatedTable === null ? '->constrained()' : '->constrained('.self::quote($relatedTable).')';

                foreach (array_intersect(self::FOREIGN_MODIFIERS, $this->modifiers) as $modifier) {
                    if ($modifier !== 'unconstrained') {
                        $line .= '->'.$modifier.'()';
                    }
                }
            }

            return $line.';';
        }

        $arguments = [self::quote($this->name)];

        if (\in_array($this->type, ['enum', 'set'], true)) {
            $arguments[] = '['.implode(', ', array_map(self::quote(...), $this->args)).']';
        } else {
            foreach ($this->args as $arg) {
                $arguments[] = self::literal($arg);
            }
        }

        $line = \sprintf('$table->%s(%s)', $this->type, implode(', ', $arguments));

        foreach (self::MODIFIERS as $modifier) {
            if (\in_array($modifier, $this->modifiers, true)) {
                $line .= '->'.$modifier.'()';
            }
        }

        if ($this->default !== null) {
            $line .= '->default('.self::literal($this->default).')';
        }

        return $line.';';
    }

    /**
     * A factory value for the column: by name when the name says what it is,
     * by type otherwise. Foreign keys and morphs are the caller's business.
     */
    public function fake(): ?string
    {
        if ($this->isBare() || $this->isMorph() || $this->isForeignKey()) {
            return null;
        }

        $fake = $this->fakeValue();

        // A unique column gets a unique fake, when the fake is Faker's to make unique.
        if (\in_array('unique', $this->modifiers, true) && str_starts_with($fake, 'fake()->')) {
            return 'fake()->unique()->'.substr($fake, \strlen('fake()->'));
        }

        return $fake;
    }

    private function fakeValue(): string
    {
        $textual = \in_array($this->type, ['char', 'string', 'tinyText', 'text', 'mediumText', 'longText'], true);

        if ($textual) {
            $byName = match (true) {
                $this->name === 'email' || str_ends_with($this->name, '_email') => 'fake()->safeEmail()',
                $this->name === 'name' || str_ends_with($this->name, '_name') => 'fake()->name()',
                $this->name === 'title' => 'fake()->sentence(3)',
                $this->name === 'slug' => 'fake()->slug()',
                $this->name === 'url' || str_ends_with($this->name, '_url') => 'fake()->url()',
                $this->name === 'phone' || str_ends_with($this->name, '_phone') => 'fake()->phoneNumber()',
                $this->name === 'password' => "bcrypt('password')",
                default => null,
            };

            if ($byName !== null) {
                return $byName;
            }
        }

        if ($this->type === 'decimal' || $this->type === 'float' || $this->type === 'double') {
            $scale = $this->type === 'decimal' ? ($this->args[1] ?? '2') : '2';

            return \sprintf('fake()->randomFloat(%s, 0, 1000)', $scale);
        }

        return match (true) {
            $this->type === 'char', $this->type === 'string' => 'fake()->words(2, true)',
            $textual => 'fake()->paragraph()',
            \in_array($this->type, self::INTEGER_TYPES, true) => 'fake()->numberBetween(1, 1000)',
            $this->type === 'boolean' => 'fake()->boolean()',
            $this->type === 'date' => 'fake()->date()',
            \in_array($this->type, ['dateTime', 'dateTimeTz', 'timestamp', 'timestampTz'], true) => 'fake()->dateTime()',
            \in_array($this->type, ['time', 'timeTz'], true) => 'fake()->time()',
            $this->type === 'year' => 'fake()->year()',
            \in_array($this->type, ['json', 'jsonb'], true) => '[]',
            $this->type === 'uuid' => 'fake()->uuid()',
            $this->type === 'ulid' => '(string) Str::ulid()',
            $this->type === 'ipAddress' => 'fake()->ipv4()',
            $this->type === 'macAddress' => 'fake()->macAddress()',
            \in_array($this->type, ['enum', 'set'], true) => 'fake()->randomElement(['.implode(', ', array_map(self::quote(...), $this->args)).'])',
            default => 'null',
        };
    }

    public static function quote(string $value): string
    {
        return "'".str_replace("'", "\\'", $value)."'";
    }

    /**
     * A default or argument as PHP: numbers and booleans bare, `null` bare,
     * everything else a single-quoted string.
     */
    public static function literal(string $value): string
    {
        $lower = strtolower($value);

        if (\in_array($lower, ['true', 'false', 'null'], true) || is_numeric($value)) {
            return $lower === 'true' || $lower === 'false' || $lower === 'null' ? $lower : $value;
        }

        return self::quote($value);
    }
}
