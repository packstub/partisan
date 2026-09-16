<?php

namespace Packstub\Partisan\Agent;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand as SymfonyHelpCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Replaces Symfony's `help` command in agent mode, so `make:model --help`
 * prints the generator's own options and nothing else.
 */
final class HelpCommand extends SymfonyHelpCommand
{
    private ?Command $target = null;

    public function __construct(private readonly CompactHelp $help)
    {
        parent::__construct();
    }

    public function setCommand(Command $command): void
    {
        $this->target = $command;

        parent::setCommand($command);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('format') !== 'txt') {
            return parent::execute($input, $output);
        }

        $command = $this->target ?? $this->getApplication()?->find((string) $input->getArgument('command_name'));

        if ($command === null) {
            return parent::execute($input, $output);
        }

        $this->help->render($command, $output);

        $this->target = null;

        return self::SUCCESS;
    }
}
