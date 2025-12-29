<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * TenantContext provides a clean API to work with the current tenant.
 *
 * This is a helper service that wraps the container bindings
 * for easier access and better IDE support.
 */
class TenantContext
{
    /**
     * Set the current tenant in the application context.
     */
    public function set(Tenant $tenant): void
    {
        app()->instance('tenant', $tenant);
    }

    /**
     * Get the current tenant from the application context.
     */
    public function get(): ?Tenant
    {
        if (! app()->bound('tenant')) {
            return null;
        }

        return app('tenant');
    }

    /**
     * Get the current tenant ID.
     */
    public function id(): ?int
    {
        return $this->get()?->id;
    }

    /**
     * Check if there is a tenant in the current context.
     */
    public function check(): bool
    {
        return $this->get() !== null;
    }

    /**
     * Clear the current tenant from the application context.
     */
    public function clear(): void
    {
        if (app()->bound('tenant')) {
            app()->forgetInstance('tenant');
        }
    }

    /**
     * Execute a callback within a specific tenant context.
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previousTenant = $this->get();

        $this->set($tenant);

        try {
            return $callback($tenant);
        } finally {
            if ($previousTenant) {
                $this->set($previousTenant);
            } else {
                $this->clear();
            }
        }
    }

    /**
     * Execute a callback without any tenant context.
     * Useful for admin operations that need to query all tenants.
     */
    public function runWithoutTenant(callable $callback): mixed
    {
        $previousTenant = $this->get();

        $this->clear();

        try {
            return $callback();
        } finally {
            if ($previousTenant) {
                $this->set($previousTenant);
            }
        }
    }

    /**
     * Get the current tenant or fail with an exception.
     *
     * @throws \RuntimeException
     */
    public function getOrFail(): Tenant
    {
        $tenant = $this->get();

        if (! $tenant) {
            throw new \RuntimeException('No tenant context available.');
        }

        return $tenant;
    }
}
