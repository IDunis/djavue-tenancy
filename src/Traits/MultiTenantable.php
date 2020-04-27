<?php

declare(strict_types=1);

namespace Djavue\Tenancy\Traits;

use Djavue\Tenancy\TenantManager;
use Djavue\Tenancy\Exceptions\ModelNotFoundForTenantException;
use Djavue\Tenancy\Exceptions\TenantColumnUnknownException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait MultiTenantable
{
    /**
     * @var TenantManager
     */
    protected static $tm;
    
    /**
     * Boot the trait. Will apply any scopes currently set, and
     * register a listener for when new models are created.
     */
    protected static function bootMultiTenantable()
    {
        static::$tm = app(TenantManager::class);

        // Add a global scope for each tenant this model should be scoped by.
        static::$tm->addTenantScopes(new static());

        // Add tenant_columns automatically when creating models
        static::creating(function (Model $model) {
            static::$tm->newTenantModel($model);
        });
    }

    /**
     * Get the tenant_columns for this model.
     *
     * @return array
     */
    public function getTenantColumns()
    {
        if (!isset($this->tenant_columns)){
            throw new TenantColumnUnknownException(
                'tenant_columns attribute was not set.'
            );
        }
        
        return $this->tenant_columns;
    }
    
    /**
     * Returns the qualified tenant (table.tenant). Override this if you need to
     * provide unqualified tenants, for example if you're using a noSQL Database.
     *
     * @param mixed $tenant
     *
     * @return mixed
     */
    public function getQualifiedTenant($tenant)
    {
        return $this->getTable().'.'.$tenant;
    }
}