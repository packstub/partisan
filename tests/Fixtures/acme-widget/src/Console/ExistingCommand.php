<?php

namespace Acme\Widget\Console;

use Illuminate\Console\Command;

class ExistingCommand extends Command
{
    protected $signature = 'widget:existing';

    protected $description = 'Pre-registered command used by partisan tests';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
