<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to validate that user session belongs to current tenant.
 *
 * Prevents session hijacking across tenants by verifying that the session's
 * tenant_id matches the current tenant context.
 */
class ValidateSessionTenant
{
    /**
     * Handle an incoming request.
     *
     * Verifies that the authenticated user's session belongs to the current tenant.
     * If mismatch detected, logs out user and redirects to login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $sessionTenantId = session('tenant_id');
            $currentTenant = app()->bound('tenant') ? app('tenant') : null;
            $currentTenantId = $currentTenant?->id;

            // Super admins can access any tenant context
            if ($request->user()->isSuperAdmin()) {
                return $next($request);
            }

            // Verify user belongs to current tenant
            if ($request->user()->tenant_id !== $currentTenantId) {
                Auth::logout();
                session()->invalidate();
                session()->regenerateToken();

                return redirect()->route('login')
                    ->with('error', 'You do not have access to this tenant.');
            }

            // If session was created for different tenant, invalidate it
            if ($sessionTenantId !== null && $sessionTenantId !== $currentTenantId) {
                Auth::logout();
                session()->invalidate();
                session()->regenerateToken();

                return redirect()->route('login')
                    ->with('error', 'Session expired. Please login again.');
            }
        }

        return $next($request);
    }
}
