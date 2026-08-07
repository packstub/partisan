<?php

namespace Packstub\Partisan\Console\Concerns;

use Packstub\Partisan\PackageContext;

trait InteractsWithPackage
{
    protected function package(): PackageContext
    {
        return $this->getLaravel()->make(PackageContext::class);
    }
}
