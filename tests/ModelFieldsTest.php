<?php

use Packstub\Partisan\Support\Fields\Field;
use Packstub\Partisan\Support\Fields\FieldSpec;
use Symfony\Component\Console\Exception\InvalidArgumentException;

describe('field spec grammar', function () {
    it('reads name:type[(args)][:modifier][=default]', function () {
        $fields = FieldSpec::parse('name:string, capacity:unsignedInteger=10,price:decimal(8,2)=0,status:enum(draft,sent)=draft,notes:text:nullable:index,user_id:foreignId:nullable:nullOnDelete');

        expect(array_map(fn (Field $field) => $field->column(), $fields))->toBe([
            "\$table->string('name');",
            "\$table->unsignedInteger('capacity')->default(10);",
            "\$table->decimal('price', 8, 2)->default(0);",
            "\$table->enum('status', ['draft', 'sent'])->default('draft');",
            "\$table->text('notes')->nullable()->index();",
            "\$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();",
        ]);
    });

    it('keeps a default that contains a colon or a space', function () {
        [$field] = FieldSpec::parse('starts_at:time=09:30');
        [$label] = FieldSpec::parse('label:string=hello world');

        expect($field->column())->toBe("\$table->time('starts_at')->default('09:30');")
            ->and($label->column())->toBe("\$table->string('label')->default('hello world');");
    });

    it('derives casts and fakes from the type, and fakes from a telling name', function () {
        $fields = FieldSpec::parse('email:string:unique,active:boolean=true,total:decimal(10,3),paid_at:timestamp:nullable,meta:json,ref:uuid,commentable:morphs,deleted_at:softDeletes');
        $byName = [];

        foreach ($fields as $field) {
            $byName[$field->name] = [$field->cast(), $field->fake(), $field->fillable()];
        }

        expect($byName)->toBe([
            'email' => [null, 'fake()->unique()->safeEmail()', ['email']],
            'active' => ['boolean', 'fake()->boolean()', ['active']],
            'total' => ['decimal:3', 'fake()->randomFloat(3, 0, 1000)', ['total']],
            'paid_at' => ['datetime', 'fake()->dateTime()', ['paid_at']],
            'meta' => ['array', '[]', ['meta']],
            'ref' => [null, 'fake()->uuid()', ['ref']],
            'commentable' => [null, null, ['commentable_type', 'commentable_id']],
            'deleted_at' => [null, null, []],
        ]);
    });

    it('names the relation and related model from the column or from foreignIdFor', function () {
        [$user, $author, $parent] = FieldSpec::parse('user_id:foreignId,author_id:foreignIdFor(User),parent_category_id:foreignId');

        expect([$user->relationName(), $user->relatedModelName()])->toBe(['user', 'User'])
            ->and([$author->relationName(), $author->relatedModelName()])->toBe(['author', 'User'])
            ->and([$parent->relationName(), $parent->relatedModelName()])->toBe(['parentCategory', 'ParentCategory']);
    });

    it('rejects what it cannot read, naming the field', function (string $spec, string $message) {
        expect(fn () => FieldSpec::parse($spec))->toThrow(InvalidArgumentException::class, $message);
    })->with([
        'unknown type' => ['name:strng', 'Unknown column type "strng" for field "name"'],
        'unknown modifier' => ['name:string:required', 'Unknown modifier "required" for field "name"'],
        'foreign modifier on a scalar' => ['name:string:cascadeOnDelete', 'only applies to foreignId columns'],
        'missing type' => ['name', 'Field "name" in --fields has no type'],
        'bad name' => ['1st:string', 'has no valid column name'],
        'foreignIdFor without a model' => ['author_id:foreignIdFor', 'needs the related model'],
        'empty' => [' , ', '--fields is empty'],
    ]);
});

describe('make:model --fields', function () {
    it('writes the model, migration and factory from one spec', function () {
        partisanExpectingSuccess('make:model', 'Invoice', '--fields=number:string:unique,total:decimal(10,2)=0,is_paid:boolean=false,notes:text:nullable', '--migration', '--factory');

        $model = assertGenerated('src/Models/Invoice.php', [
            "protected \$fillable = [\n        'number',\n        'total',\n        'is_paid',\n        'notes',\n    ];",
            "protected function casts(): array\n    {\n        return [\n            'total' => 'decimal:2',\n            'is_paid' => 'boolean',\n        ];\n    }",
        ]);

        expect($model)->not->toContain('BelongsTo');

        [$migration] = glob(test()->fixture.'/database/migrations/*_create_invoices_table.php');

        expect((string) file_get_contents($migration))->toContain(implode("\n", [
            '            $table->id();',
            "            \$table->string('number')->unique();",
            "            \$table->decimal('total', 10, 2)->default(0);",
            "            \$table->boolean('is_paid')->default(false);",
            "            \$table->text('notes')->nullable();",
            '            $table->timestamps();',
        ]));

        assertGenerated('database/factories/InvoiceFactory.php', [
            "            'number' => fake()->unique()->words(2, true),",
            "            'total' => fake()->randomFloat(2, 0, 1000),",
            "            'is_paid' => fake()->boolean(),",
            "            'notes' => fake()->paragraph(),",
        ]);

        // The migration the spec produced runs against SQLite.
        $check = partisanWithEnv(['PARTISAN_AGENT' => '1'], 'partisan:check');

        expect($check->getOutput())->toContain('migrations: pass — 1 applied');
    });

    it('writes a belongsTo per foreign key and a morphTo per morph, resolving the related class in order', function () {
        partisanExpectingSuccess('make:model', 'Customer');

        $process = partisanWithEnv(['PARTISAN_AGENT' => '1'], 'make:model', 'Invoice', '--fields=user_id:foreignId,customer_id:foreignIdFor(Customer):nullable:nullOnDelete,team_id:foreignId,owner_id:foreignIdFor(App\\Models\\Owner),commentable:morphs', '--migration', '--factory');

        expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput());

        assertGenerated('src/Models/Invoice.php', [
            'use Illuminate\Database\Eloquent\Relations\BelongsTo;',
            'use Illuminate\Database\Eloquent\Relations\MorphTo;',
            'use App\Models\Owner;',
            "public function user(): BelongsTo\n    {\n        return \$this->belongsTo(config('auth.providers.users.model'));\n    }",
            "public function customer(): BelongsTo\n    {\n        return \$this->belongsTo(Customer::class);\n    }",
            "public function team(): BelongsTo\n    {\n        return \$this->belongsTo(Team::class);\n    }",
            "public function owner(): BelongsTo\n    {\n        return \$this->belongsTo(Owner::class);\n    }",
            "public function commentable(): MorphTo\n    {\n        return \$this->morphTo();\n    }",
            "'commentable_type',\n        'commentable_id',",
        ]);

        [$migration] = glob(test()->fixture.'/database/migrations/*_create_invoices_table.php');

        expect((string) file_get_contents($migration))
            ->toContain("\$table->foreignId('user_id')->constrained();")
            ->toContain("\$table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();")
            ->toContain("\$table->foreignId('team_id')->constrained();")
            ->toContain("\$table->foreignId('owner_id')->constrained('owners');")
            ->toContain("\$table->morphs('commentable');");

        assertGenerated('database/factories/InvoiceFactory.php', [
            'use Acme\Widget\Models\Customer;',
            'use Acme\Widget\Models\Team;',
            'use App\Models\Owner;',
            "'user_id' => fn () => config('auth.providers.users.model')::factory(),",
            "'customer_id' => Customer::factory(),",
            "'team_id' => Team::factory(),",
            "'owner_id' => Owner::factory(),",
        ]);

        // Only Team is unknown: Customer exists in the package, Owner was named
        // with its namespace, User is the host app's.
        expect($process->getOutput())
            ->toContain("unresolved[1]:\n  - Acme\\Widget\\Models\\Team (relation team() on Invoice): no such class in the package")
            ->not->toContain('Customer (relation')
            ->not->toContain('Owner (relation');
    });

    it('warns people about an unresolved relation without the agent list', function () {
        $process = partisanExpectingSuccess('make:model', 'Invoice', '--fields=team_id:foreignId');

        expect($process->getOutput())->toContain('Acme\Widget\Models\Team does not exist yet')->not->toContain('unresolved[');
    });

    it('adds columns to an alter migration on its own', function () {
        partisanExpectingSuccess('make:migration', 'add_reference_to_invoices_table', '--table=invoices', '--fields=reference:string(40):nullable:index');

        [$migration] = glob(test()->fixture.'/database/migrations/*_add_reference_to_invoices_table.php');

        expect((string) file_get_contents($migration))
            ->toContain("Schema::table('invoices', function (Blueprint \$table) {\n            \$table->string('reference', 40)->nullable()->index();\n        });");
    });

    it('fills a factory generated on its own', function () {
        partisanExpectingSuccess('make:factory', 'InvoiceFactory', '--model=Invoice', '--fields=number:string,ref:ulid');

        assertGenerated('database/factories/InvoiceFactory.php', [
            'use Illuminate\Support\Str;',
            "'number' => fake()->words(2, true),",
            "'ref' => (string) Str::ulid(),",
        ]);
    });

    it('rejects a bad spec with the generator usage in agent mode and writes nothing', function () {
        $process = partisanWithEnv(['PARTISAN_AGENT' => '1'], 'make:model', 'Invoice', '--fields=name:strng', '--migration');
        $output = $process->getOutput().$process->getErrorOutput();

        expect($process->getExitCode())->toBe(1)
            ->and($output)
            ->toContain('Unknown column type "strng" for field "name"')
            ->toContain('usage: vendor/bin/partisan make:model <name> [options]')
            ->toContain('--fields=FIELDS')
            ->and(is_file(test()->fixture.'/src/Models/Invoice.php'))->toBeFalse()
            ->and(glob(test()->fixture.'/database/migrations/*.php') ?: [])->toBe([]);
    });

    it('shows the grammar in the compact help', function () {
        $process = partisanWithEnv(['PARTISAN_AGENT' => '1'], 'make:migration', '--help');

        expect($process->getOutput())->toContain('--fields=FIELDS    Columns as name:type[(args)][:modifier][=default]');
    });
});
