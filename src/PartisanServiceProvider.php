<?php

namespace Packstub\Partisan;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Orchestra\Canvas\Console\ConsoleMakeCommand as CanvasConsoleMakeCommand;
use Orchestra\Canvas\Console\FactoryMakeCommand as CanvasFactoryMakeCommand;
use Orchestra\Canvas\Console\ModelMakeCommand as CanvasModelMakeCommand;
use Orchestra\Canvas\Console\TestMakeCommand as CanvasTestMakeCommand;
use Orchestra\Canvas\Core\PresetManager;
use Packstub\Partisan\Console\AboutCommand;
use Packstub\Partisan\Console\ConsoleMakeCommand;
use Packstub\Partisan\Console\FactoryMakeCommand;
use Packstub\Partisan\Console\ModelMakeCommand;
use Packstub\Partisan\Console\TestMakeCommand;

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
        ]);
    }
}
