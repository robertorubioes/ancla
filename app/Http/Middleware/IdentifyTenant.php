<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * Dominios excluidos del tenant resolution (admin, api global, etc.).
     *
     * @var array<string>
     */
    protected array $excludedSubdomains = [
        'admin',
        'api',
        'www',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if (! $tenant) {
            abort(404, 'Tenant not found');
        }

        if ($tenant->isSuspended()) {
            abort(403, 'Account suspended. Please contact support.');
        }

        if ($tenant->hasTrialExpired()) {
            abort(403, 'Trial period expired. Please upgrade your plan.');
        }

        // Registrar tenant en el container para acceso global
        app()->instance('tenant', $tenant);

        // Añadir tenant a la vista para acceso en Blade
        view()->share('tenant', $tenant);

        return $next($request);
    }

    /**
     * Resolver el tenant desde la request.
     * Prioridad: 1. Subdominio, 2. Dominio custom, 3. Header.
     */
    protected function resolveTenant(Request $request): ?Tenant
    {
        // 1. Intentar por subdominio
        $host = $request->getHost();
        $subdomain = $this->extractSubdomain($host);

        if ($subdomain && ! in_array($subdomain, $this->excludedSubdomains)) {
            $tenant = $this->findTenantBySlug($subdomain);
            if ($tenant) {
                return $tenant;
            }
        }

        // 2. Intentar por dominio personalizado
        $tenant = $this->findTenantByDomain($host);
        if ($tenant) {
            return $tenant;
        }

        // 3. Fallback a header (para testing/APIs)
        if ($tenantId = $request->header('X-Tenant-ID')) {
            return $this->findTenantById($tenantId);
        }

        return null;
    }

    /**
     * Extraer subdominio del host.
     */
    protected function extractSubdomain(string $host): ?string
    {
        $baseDomain = config('app.base_domain', 'ancla.app');

        // Si estamos en localhost, extraer subdomain diferente
        if (str_contains($host, 'localhost')) {
            // Formato: subdomain.localhost o subdomain.localhost:8000
            $parts = explode('.', $host);
            if (count($parts) >= 2 && $parts[1] === 'localhost') {
                return $parts[0];
            }

            return null;
        }

        if (str_ends_with($host, $baseDomain)) {
            $subdomain = str_replace('.'.$baseDomain, '', $host);

            return $subdomain !== $baseDomain ? $subdomain : null;
        }

        return null;
    }

    /**
     * Buscar tenant por subdomain con cache.
     */
    protected function findTenantBySlug(string $subdomain): ?Tenant
    {
        return Cache::remember(
            "tenant:subdomain:{$subdomain}",
            now()->addMinutes(60),
            fn () => Tenant::where('subdomain', $subdomain)->first()
        );
    }

    /**
     * Buscar tenant por dominio personalizado.
     */
    protected function findTenantByDomain(string $domain): ?Tenant
    {
        return Cache::remember(
            "tenant:domain:{$domain}",
            now()->addMinutes(60),
            fn () => Tenant::where('domain', $domain)->first()
        );
    }

    /**
     * Buscar tenant por ID (para header).
     */
    protected function findTenantById(string $id): ?Tenant
    {
        return Cache::remember(
            "tenant:id:{$id}",
            now()->addMinutes(60),
            fn () => Tenant::find($id)
        );
    }
}
