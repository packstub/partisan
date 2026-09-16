<?php

namespace Packstub\Partisan\Formatting;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Packstub\Partisan\Agent\AgentMode;
use Packstub\Partisan\Agent\PackageSnapshot;
use Packstub\Partisan\PackageContext;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Stubs are written for a Laravel application, not for a package's pint.json:
 * after a generator runs, the files it created or updated go through the
 * package's own Pint (when installed) so they match the package style —
 * strict types declaration, import order, docblock class names — before
 * anyone reads them. `PARTISAN_PINT=0` opts out.
 */
final class FormatsGeneratedFiles
{
    private ?PackageSnapshot $snapshot = null;

    public function __construct(private readonly PackageContext $package) {}

    public function starting(CommandStarting $event): void
    {
        if (! str_starts_with((string) $event->command, 'make:') || ! $this->enabled()) {
            return;
        }

        $this->snapshot = PackageSnapshot::take($this->package->rootPath);
    }

    public function finished(CommandFinished $event): void
    {
        if ($this->snapshot === null) {
            return;
        }

        $changes = $this->snapshot->changes();
        $this->snapshot = null;

        $files = array_values(array_filter(
            [...$changes['created'], ...$changes['updated']],
            static fn (string $path): bool => str_ends_with($path, '.php'),
        ));

        if ($files === []) {
            return;
        }

        $pint = $this->package->rootPath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pint';

        if (! is_file($pint)) {
            return;
        }

        try {
            $process = new Process([PHP_BINARY, $pint, ...$files], $this->package->rootPath, timeout: 120);
            $process->run();
            $error = $process->isSuccessful() ? null : trim($process->getErrorOutput().$process->getOutput());
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        if ($error === null) {
            if (AgentMode::enabled()) {
                $event->output->writeln(\sprintf('formatted[%d]: pint, with the package\'s pint.json', count($files)));
            }

            return;
        }

        $reason = strtok($error, "\n") ?: 'pint exited with an error';

        $event->output->writeln(AgentMode::enabled()
            ? 'formatted[0]: pint skipped — '.$reason
            : '<comment>Pint could not format the generated files: '.$reason.'</comment>');
    }

    private function enabled(): bool
    {
        $flag = getenv('PARTISAN_PINT');

        return ! (is_string($flag) && trim($flag) !== '' && ! filter_var($flag, FILTER_VALIDATE_BOOLEAN));
    }
}
