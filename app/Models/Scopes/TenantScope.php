<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * Solo aplica el filtro si hay un tenant en contexto.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Solo aplicar si hay tenant en contexto
        if (app()->bound('tenant') && $tenant = app('tenant')) {
            $builder->where($model->getTable().'.tenant_id', $tenant->id);
        }
    }
}
