<?php

namespace Packstub\Partisan\Agent;

use Packstub\Partisan\PackageContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A command's own arguments and options in a few lines, without the
 * framework-wide options (--env, --ansi, -v|vv|vvv, …) that make Symfony's
 * help four times longer than what an agent needs to pick the right flags.
 */
final class CompactHelp
{
    public function __construct(private readonly PackageContext $package) {}

    public function render(Command $command, OutputInterface $output): void
    {
        $definition = $command->getNativeDefinition();
        $arguments = array_values($definition->getArguments());
        $options = array_values(array_filter($definition->getOptions(), static fn (InputOption $option): bool => $option->getName() !== 'help'));

        $description = trim((string) $command->getDescription());
        $output->writeln($description === '' ? (string) $command->getName() : \sprintf('%s — %s', $command->getName(), $description));

        $usage = [$this->package->artisan(), (string) $command->getName()];

        foreach ($arguments as $argument) {
            $usage[] = $argument->isRequired() ? \sprintf('<%s>', $argument->getName()) : \sprintf('[<%s>]', $argument->getName());
        }

        if ($options !== []) {
            $usage[] = '[options]';
        }

        $output->writeln('usage: '.implode(' ', $usage));

        $this->table($output, 'arguments', array_map(
            static fn (InputArgument $argument): array => [$argument->getName(), self::describe($argument->getDescription(), $argument->getDefault())],
            $arguments,
        ));

        $this->table($output, 'options', array_map(
            static fn (InputOption $option): array => [self::optionSynopsis($option), self::describe($option->getDescription(), $option->getDefault())],
            $options,
        ));
    }

    /**
     * @param  list<array{0: string, 1: string}>  $rows
     */
    private function table(OutputInterface $output, string $key, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $output->writeln(\sprintf('%s[%d]:', $key, count($rows)));

        $width = max(array_map(static fn (array $row): int => mb_strlen($row[0]), $rows));

        foreach ($rows as [$name, $description]) {
            $output->writeln(rtrim(\sprintf('  %s  %s', str_pad($name, $width), $description)));
        }
    }

    private static function optionSynopsis(InputOption $option): string
    {
        $name = '--'.$option->getName();

        if ($option->acceptValue()) {
            $value = strtoupper($option->getName());
            $name .= $option->isValueRequired() ? '='.$value : '[='.$value.']';
        }

        return $option->getShortcut() !== null ? \sprintf('-%s, %s', $option->getShortcut(), $name) : $name;
    }

    private static function describe(string $description, mixed $default): string
    {
        $description = trim(preg_replace('/<\/?\w+>/', '', $description) ?? $description);

        if ($default !== null && $default !== false && $default !== [] && $default !== '') {
            $description .= \sprintf(' [default: %s]', is_array($default) ? implode(',', $default) : (string) $default);
        }

        return $description;
    }
}
