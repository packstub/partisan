<?php

namespace Acme\Widget;

use Acme\Widget\Console\ExistingCommand;
use Illuminate\Support\ServiceProvider;

class WidgetServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->commands([
            ExistingCommand::class,
        ]);
    }
}
