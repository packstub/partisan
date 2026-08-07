<?php

it('wires generated models to their package factory', function () {
    partisanExpectingSuccess('make:model', 'Order', '--factory');

    $model = assertGenerated('src/Models/Order.php', [
        'namespace Acme\Widget\Models;',
        '/** @use HasFactory<\Acme\Widget\Database\Factories\OrderFactory> */',
        'use Illuminate\Database\Eloquent\Attributes\UseFactory;',
        'use Acme\Widget\Database\Factories\OrderFactory;',
        '#[UseFactory(OrderFactory::class)]',
    ]);

    // The app-convention reference must be gone and the attribute added once.
    expect($model)->not->toContain('<\Database\Factories\\')
        ->and(substr_count($model, '#[UseFactory'))->toBe(1);

    assertGenerated('database/factories/OrderFactory.php', [
        'namespace Acme\Widget\Database\Factories;',
        'use Acme\Widget\Models\Order;',
    ]);
});

it('does not touch models generated without a factory', function () {
    partisanExpectingSuccess('make:model', 'Plain');

    $model = assertGenerated('src/Models/Plain.php', ['namespace Acme\Widget\Models;']);

    expect($model)->not->toContain('UseFactory')
        ->and($model)->not->toContain('Database\Factories');
});

it('qualifies --model against the package model namespace before the model exists', function () {
    partisanExpectingSuccess('make:factory', 'OrderFactory', '--model=Order');

    assertGenerated('database/factories/OrderFactory.php', [
        'namespace Acme\Widget\Database\Factories;',
        'use Acme\Widget\Models\Order;',
    ]);
});
