<?php

declare(strict_types=1);

namespace Tenancy\Facades;

use Illuminate\Support\Facades\Facade;

class Tenancy extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return TenantManager::class;
    }
}

