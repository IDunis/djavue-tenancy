<?php

declare(strict_types=1);

namespace Djavue\Tenancy;

use Djavue\Tenancy\Exceptions\TenantColumnUnknownException;
use Djavue\Tenancy\Exceptions\TenantNullIdException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;

class TenantManager
{
    use Macroable;
    
    /**
     * @var Collection
     */
    protected $tenants;
    
    /**
     * @var Collection
     */
    protected $deferredModels;
    
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->tenants = collect();
        $this->deferredModels = collect();
    }
    
    /**
     * Add a tenant to scope by.
     *
     * @param string|Model $tenant
     * @param mixed|null   $id
     *
     * @throws TenantNullIdException
     */
    public function addTenant($tenant, $id = null)
    {
        if (func_num_args() == 1 && $tenant instanceof Model) {
            $id = $tenant->getKey();
        }

        if (is_null($id)) {
            throw new TenantNullIdException('$id must not be null');
        }

        $this->tenants->put($this->getTenantKey($tenant), $id);
    }
    
    /**
     * Whether a tenant is currently being scoped.
     *
     * @param string|Model $tenant
     *
     * @return bool
     */
    public function hasTenant($tenant) :bool
    {
        return $this->tenants->has($this->getTenantKey($tenant));
    }

    /**
     * @return Collection
     */
    public function getTenants()
    {
        return $this->tenants;
    }

    /**
     * Add applicable tenant scopes to a model.
     *
     * @param Model|MultiTenantable $model
     */
    public function addTenantScopes(Model $model)
    {

        if ($this->tenants->isEmpty()) {
            // No tenants yet, defer scoping to a later stage
            $this->deferredModels->push($model);

            return;
        }

        $this->modelTenants($model)->each(function ($id, $tenant) use ($model) {
            $model->addGlobalScope($tenant, function (Builder $builder) use ($tenant, $id, $model) {
                if ($this->getTenants()->first() && $this->getTenants()->first() != $id) {
                    $id = $this->getTenants()->first();
                }

                $builder->where($model->getQualifiedTenant($tenant), '=', $id);
            });
        });
    }
    
    /**
     * Add tenant columns as needed to a new model instance before it is created.
     *
     * @param Model $model
     */
    public function newTenantModel(Model $model)
    {

        if ($this->tenants->isEmpty()) {
            // No tenants yet, defer scoping to a later stage
            $this->deferredModels->push($model);

            return;
        }

        $this->modelTenants($model)->each(function ($tenantId, $tenantColumn) use ($model) {
            if (!isset($model->{$tenantColumn})) {
                $model->setAttribute($tenantColumn, $tenantId);
            }
        });
    }
    
    /**
     * Get the key for a tenant, either from a Model instance or a string.
     *
     * @param string|Model $tenant
     *
     * @throws TenantColumnUnknownException
     *
     * @return string
     */
    protected function getTenantKey($tenant) :string
    {
        if ($tenant instanceof Model) {
            $tenant = $tenant->getForeignKey();
        }

        if (!is_string($tenant)) {
            throw new TenantColumnUnknownException(
                '$tenant must be a string key or an instance of \Illuminate\Database\Eloquent\Model'
            );
        }

        return $tenant;
    }

    /**
     * Get the tenant_columns that are actually applicable to the given
     * model, in case they've been manually specified.
     *
     * @param Model|MultiTenantable $model
     *
     * @return Collection
     */
    protected function modelTenants(Model $model)
    {
        return $this->tenants->only($model->getTenantColumns());
    }
}