<?php

namespace Packstub\Partisan\Agent;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Packstub\Partisan\PackageContext;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Agent-mode console behaviour: no prompts, no ANSI, and after each command
 * a plain list of the files it wrote, so the agent never needs a second
 * command to learn what a generator produced.
 */
final class AgentOutput
{
    private ?PackageSnapshot $snapshot = null;

    public function __construct(private readonly PackageContext $package) {}

    public function starting(CommandStarting $event): void
    {
        $event->input->setInteractive(false);
        $event->output->setDecorated(false);

        $this->snapshot = PackageSnapshot::take($this->package->rootPath);
    }

    public function finished(CommandFinished $event): void
    {
        if ($this->snapshot === null || $event->input->hasParameterOption(['--help', '-h'], true)) {
            $this->snapshot = null;

            return;
        }

        $changes = $this->snapshot->changes();
        $this->snapshot = null;

        $generator = str_starts_with($event->command, 'make:');

        if ($changes['created'] === [] && $changes['updated'] === [] && ! $generator) {
            return;
        }

        $this->writeList($event->output, 'created', $changes['created'], $generator ? 'no files written' : null);
        $this->writeList($event->output, 'updated', $changes['updated']);

        if ($generator && $event->exitCode === 0) {
            $event->output->writeln('help[1]:');
            $event->output->writeln(\sprintf('  Run `%s %s --help` for this generator\'s options', $this->package->artisan(), $event->command));
        }
    }

    /**
     * @param  list<string>  $paths
     */
    private function writeList(OutputInterface $output, string $key, array $paths, ?string $empty = null): void
    {
        if ($paths === []) {
            if ($empty !== null) {
                $output->writeln(\sprintf('%s[0]: %s', $key, $empty));
            }

            return;
        }

        $output->writeln(\sprintf('%s[%d]:', $key, count($paths)));

        foreach ($paths as $path) {
            $output->writeln('  - '.$path);
        }
    }
}
