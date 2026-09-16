<?php

namespace Packstub\Partisan\Agent;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Debug\ExceptionHandler as Contract;
use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Wraps the application's exception handler in agent mode: after an input
 * error ("The --model option does not exist", a missing argument) the
 * command's compact usage follows the message, so the retry needs no
 * separate --help run.
 */
final class AgentExceptionHandler implements Contract
{
    public function __construct(
        private readonly Contract $inner,
        private readonly Application $app,
        private readonly CompactHelp $help,
    ) {}

    public function report(Throwable $e): void
    {
        $this->inner->report($e);
    }

    public function shouldReport(Throwable $e): bool
    {
        return $this->inner->shouldReport($e);
    }

    public function render($request, Throwable $e)
    {
        return $this->inner->render($request, $e);
    }

    public function renderForConsole($output, Throwable $e): void
    {
        $this->inner->renderForConsole($output, $e);

        if (! $e instanceof ConsoleException || $e instanceof CommandNotFoundException || ! $output instanceof OutputInterface) {
            return;
        }

        $name = self::commandName();

        if ($name === null) {
            return;
        }

        try {
            $command = $this->app->make(Kernel::class)->all()[$name] ?? null;
        } catch (Throwable) {
            return;
        }

        if ($command === null) {
            return;
        }

        $output->writeln('');
        $this->help->render($command, $output);
    }

    /**
     * The command named on the command line, if any.
     */
    private static function commandName(): ?string
    {
        /** @var array<int, string> $argv */
        $argv = $_SERVER['argv'] ?? [];

        foreach (array_slice($argv, 1) as $argument) {
            if ($argument !== '' && ! str_starts_with($argument, '-')) {
                return $argument;
            }
        }

        return null;
    }
}
