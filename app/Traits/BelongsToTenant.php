<?php

namespace App\Traits;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
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

        // Auto-asignar tenant_id al crear
        static::creating(function ($model): void {
            if (app()->bound('tenant') && app('tenant')) {
                $model->tenant_id = app('tenant')->id;
            }
        });
    }

    /**
     * Relación con el tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope para query sin filtro de tenant (uso admin).
     */
    public function scopeWithoutTenantScope($query)
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Scope para filtrar por un tenant específico.
     */
    public function scopeForTenant($query, Tenant|int $tenant)
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
