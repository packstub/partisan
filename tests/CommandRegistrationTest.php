<?php

function widgetProvider(): string
{
    return (string) file_get_contents(test()->fixture.'/src/WidgetServiceProvider.php');
}

it('registers generated commands in the package service provider', function () {
    partisanExpectingSuccess('make:command', 'SyncInvoices');

    $provider = widgetProvider();

    expect($provider)
        ->toContain('use Acme\Widget\Console\SyncInvoices;')
        ->toContain('SyncInvoices::class,')
        ->toContain('ExistingCommand::class,');
});

it('does not duplicate an already-registered command', function () {
    partisanExpectingSuccess('make:command', 'SyncInvoices');
    partisanExpectingSuccess('make:command', 'SyncInvoices', '--force');

    expect(substr_count(widgetProvider(), 'SyncInvoices::class'))->toBe(1)
        ->and(substr_count(widgetProvider(), 'use Acme\Widget\Console\SyncInvoices;'))->toBe(1);
});

it('leaves the provider untouched with --no-register', function () {
    $before = widgetProvider();

    partisanExpectingSuccess('make:command', 'SyncInvoices', '--no-register');

    expect(widgetProvider())->toBe($before);
});

it('registers into spatie-style hasCommands blocks', function () {
    file_put_contents(test()->fixture.'/src/WidgetServiceProvider.php', <<<'PHP'
    <?php

    namespace Acme\Widget;

    use Acme\Widget\Console\ExistingCommand;
    use Illuminate\Support\ServiceProvider;

    class WidgetServiceProvider extends ServiceProvider
    {
        public function configurePackage(object $package): void
        {
            $package->hasCommands([
                ExistingCommand::class,
            ]);
        }
    }
    PHP);

    partisanExpectingSuccess('make:command', 'SyncInvoices');

    expect(widgetProvider())
        ->toContain('use Acme\Widget\Console\SyncInvoices;')
        ->toMatch('/hasCommands\(\[\n\s+SyncInvoices::class,\n\s+ExistingCommand::class,/');
});

it('appends a commands block to boot() when the provider has none', function () {
    file_put_contents(test()->fixture.'/src/WidgetServiceProvider.php', <<<'PHP'
    <?php

    namespace Acme\Widget;

    use Illuminate\Support\ServiceProvider;

    class WidgetServiceProvider extends ServiceProvider
    {
        public function boot(): void
        {
            $this->loadViewsFrom(__DIR__.'/../resources/views', 'widget');
        }
    }
    PHP);

    partisanExpectingSuccess('make:command', 'SyncInvoices');

    expect(widgetProvider())
        ->toContain('use Acme\Widget\Console\SyncInvoices;')
        ->toMatch('/\$this->commands\(\[\n\s+SyncInvoices::class,\n\s+\]\);/');
});

it('expands an empty inline commands array', function () {
    file_put_contents(test()->fixture.'/src/WidgetServiceProvider.php', <<<'PHP'
    <?php

    namespace Acme\Widget;

    use Illuminate\Support\ServiceProvider;

    class WidgetServiceProvider extends ServiceProvider
    {
        public function boot(): void
        {
            $this->commands([]);
        }
    }
    PHP);

    partisanExpectingSuccess('make:command', 'SyncInvoices');

    expect(widgetProvider())->toMatch('/\$this->commands\(\[\n\s+SyncInvoices::class,\n\s+\]\);/');
});
