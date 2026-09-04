<?php

namespace App\Traits;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the trait.
     * Registra el Global Scope y el Observer automáticamente.
     */
    protected static function bootBelongsToTenant(): void
    {
        // Añadir scope global para filtrar por tenant
        static::addGlobalScope(new TenantScope);

        // Auto-asignar tenant_id al crear (solo si no viene ya asignado)
        static::creating(function ($model): void {
            if (! $model->tenant_id && app()->bound('tenant') && app('tenant')) {
                $model->tenant_id = app('tenant')->id;
            }
        });
    }

    /**
     * Relación con el tenant.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope para query sin filtro de tenant (uso admin).
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Scope para filtrar por un tenant específico.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeForTenant(Builder $query, Tenant|int $tenant): Builder
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $query->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId);
    }

    /**
     * Verificar si pertenece a un tenant específico.
     */
    public function belongsToTenant(Tenant|int $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $this->tenant_id === $tenantId;
    }
}
