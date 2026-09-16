<?php

namespace Packstub\Partisan;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Orchestra\Canvas\Console\ConsoleMakeCommand as CanvasConsoleMakeCommand;
use Orchestra\Canvas\Console\FactoryMakeCommand as CanvasFactoryMakeCommand;
use Orchestra\Canvas\Console\ModelMakeCommand as CanvasModelMakeCommand;
use Orchestra\Canvas\Console\TestMakeCommand as CanvasTestMakeCommand;
use Orchestra\Canvas\Core\PresetManager;
use Packstub\Partisan\Agent\AgentExceptionHandler;
use Packstub\Partisan\Agent\AgentMode;
use Packstub\Partisan\Agent\AgentOutput;
use Packstub\Partisan\Agent\CompactHelp;
use Packstub\Partisan\Agent\HelpCommand;
use Packstub\Partisan\Console\AboutCommand;
use Packstub\Partisan\Console\CheckCommand;
use Packstub\Partisan\Console\ConsoleMakeCommand;
use Packstub\Partisan\Console\FactoryMakeCommand;
use Packstub\Partisan\Console\InstallCommand;
use Packstub\Partisan\Console\ModelMakeCommand;
use Packstub\Partisan\Console\TestMakeCommand;
use Packstub\Partisan\Database\MigratesForGenerators;
use Packstub\Partisan\Formatting\FormatsGeneratedFiles;

class PartisanServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Canvas routes every make: command through the active generator
        // preset. Registering ours and making it the default points all
        // generators at the package; --preset=workbench and --preset=laravel
        // remain available per command.
        $this->callAfterResolving(PresetManager::class, static function (PresetManager $manager, Application $app) {
            $manager->extend('partisan', static fn () => new GeneratorPreset($app, $app->make(PackageContext::class)));
            $manager->setDefaultDriver('partisan');
        });

        $this->app->extend(CanvasConsoleMakeCommand::class, static fn ($command, $app) => new ConsoleMakeCommand($app['files']));
        $this->app->extend(CanvasFactoryMakeCommand::class, static fn ($command, $app) => new FactoryMakeCommand($app['files']));
        $this->app->extend(CanvasModelMakeCommand::class, static fn ($command, $app) => new ModelMakeCommand($app['files']));
        $this->app->extend(CanvasTestMakeCommand::class, static fn ($command, $app) => new TestMakeCommand($app['files']));

        $this->commands([
            AboutCommand::class,
            CheckCommand::class,
            InstallCommand::class,
        ]);
    }

    public function boot(): void
    {
        $events = $this->app->make(Dispatcher::class);

        // A Filament generator run with --generate reads the model's table:
        // give it one by migrating the package into an in-memory SQLite.
        $events->listen(CommandStarting::class, fn (CommandStarting $event) => (new MigratesForGenerators(
            $this->app,
            $this->app->make(PackageContext::class),
        ))->starting($event));

        // Generated and updated files go through the package's own Pint, so
        // stubs match the package style before anyone reads them. Registered
        // before the agent-mode report so the report describes formatted files.
        $this->app->singleton(FormatsGeneratedFiles::class);
        $events->listen(CommandStarting::class, fn (CommandStarting $event) => $this->app->make(FormatsGeneratedFiles::class)->starting($event));
        $events->listen(CommandFinished::class, fn (CommandFinished $event) => $this->app->make(FormatsGeneratedFiles::class)->finished($event));

        if (! AgentMode::enabled()) {
            return;
        }

        // Driven by an AI coding agent: never prompt, drop ANSI, and report
        // the files each command wrote (see Agent\AgentOutput).
        $this->app->singleton(AgentOutput::class);

        $events->listen(CommandStarting::class, fn (CommandStarting $event) => $this->app->make(AgentOutput::class)->starting($event));
        $events->listen(CommandFinished::class, fn (CommandFinished $event) => $this->app->make(AgentOutput::class)->finished($event));

        // `make:model --help` shows the generator's own options only, and an
        // input error is followed by that same usage (see Agent\CompactHelp).
        $this->app->singleton(CompactHelp::class);
        $this->commands([HelpCommand::class]);
        $this->app->extend(ExceptionHandler::class, fn (ExceptionHandler $handler) => new AgentExceptionHandler($handler, $this->app, $this->app->make(CompactHelp::class)));
    }
}
