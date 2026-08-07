<?php

namespace Packstub\Partisan\Console;

use Orchestra\Canvas\Console\ConsoleMakeCommand as CanvasConsoleMakeCommand;
use Packstub\Partisan\Console\Concerns\InteractsWithPackage;
use Packstub\Partisan\Support\ProviderCommandRegistrar;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:command', description: 'Create a new Artisan command')]
class ConsoleMakeCommand extends CanvasConsoleMakeCommand
{
    use InteractsWithPackage;

    protected function configure(): void
    {
        parent::configure();

        $this->getDefinition()->addOption(new InputOption(
            'no-register',
            null,
            InputOption::VALUE_NONE,
            'Do not register the generated command in the package service provider',
        ));
    }

    /**
     * A generated command only becomes usable once the package's service
     * provider registers it — offer to do that (confirmed interactively,
     * automatic under --no-interaction, skippable with --no-register).
     */
    public function afterCodeHasBeenGenerated(string $className, string $path): void
    {
        parent::afterCodeHasBeenGenerated($className, $path);

        if ($this->option('no-register') === true) {
            return;
        }

        $registrar = new ProviderCommandRegistrar($this->package());
        $target = $registrar->locate();

        if ($target === null) {
            return;
        }

        $provider = class_basename($target['provider']);

        if (! $this->confirm("Register [".class_basename($className)."] in [{$provider}]?", true)) {
            return;
        }

        match ($registrar->register($target['file'], $className)) {
            'registered' => $this->components->info("Command registered in [{$provider}]."),
            'already' => $this->components->info("Command already registered in [{$provider}]."),
            'no-block' => $this->components->warn(
                "Couldn't find a registration point in [{$provider}] — add it manually: ".$registrar->snippet($className),
            ),
        };
    }
}
