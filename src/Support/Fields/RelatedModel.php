<?php

namespace Packstub\Partisan\Support\Fields;

use Illuminate\Support\Str;

/**
 * Where a foreign key points. A package cannot know the host application's
 * classes, so the related model is resolved in order: a class in the package's
 * model namespace that exists; `User` with no package class, which is the host
 * app's user and goes through `config('auth.providers.users.model')` (the idiom
 * Filament and Filament Teams use); a backslashed name, taken verbatim; and
 * otherwise the package namespace, written and reported as unresolved so the
 * agent points it at the host model or a config key.
 */
final class RelatedModel
{
    public const AUTH_USER = "config('auth.providers.users.model')";

    private function __construct(
        public readonly string $name,
        public readonly ?string $class,
        public readonly bool $viaAuthConfig,
        public readonly bool $unresolved,
    ) {}

    public static function resolve(string $name, string $modelNamespace): self
    {
        $modelNamespace = rtrim($modelNamespace, '\\');

        if (str_contains($name, '\\')) {
            return new self(class_basename($name), ltrim($name, '\\'), false, false);
        }

        $class = $modelNamespace.'\\'.Str::studly($name);

        if (self::exists($class)) {
            return new self(class_basename($class), $class, false, false);
        }

        if (Str::studly($name) === 'User') {
            return new self('User', null, true, false);
        }

        return new self(class_basename($class), $class, false, true);
    }

    /** The table a `constrained()` call needs, derived from the model name. */
    public function table(): string
    {
        return Str::snake(Str::pluralStudly($this->name));
    }

    /** The argument for `$this->belongsTo(…)`. */
    public function relationArgument(): string
    {
        return $this->viaAuthConfig ? self::AUTH_USER : $this->name.'::class';
    }

    /** The factory value for the foreign key column. */
    public function factoryValue(): string
    {
        return $this->viaAuthConfig ? 'fn () => '.self::AUTH_USER.'::factory()' : $this->name.'::factory()';
    }

    /**
     * The import a file in $namespace needs to name this model, or null when
     * none (same namespace, or resolved through config).
     */
    public function importFor(string $namespace): ?string
    {
        if ($this->class === null) {
            return null;
        }

        return Str::beforeLast($this->class, '\\') === rtrim($namespace, '\\') ? null : $this->class;
    }

    private static function exists(string $class): bool
    {
        try {
            return class_exists($class);
        } catch (\Throwable) {
            return false;
        }
    }
}
