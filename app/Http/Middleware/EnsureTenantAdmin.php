<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAdmin
{
    /**
     * Handle an incoming request.
     *
     * Ensures that the authenticated user is a tenant admin with manage_users permission.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // User must be authenticated
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        // Superadmins have full access
        if ($user->role->value === 'super_admin') {
            return $next($request);
        }

        // User must belong to a tenant (not superadmin)
        if (! $user->tenant_id) {
            abort(403, 'This action requires a tenant context');
        }

        // User must have manage_users permission
        if (! $user->hasPermission(Permission::MANAGE_USERS)) {
            abort(403, 'You do not have permission to manage users');
        }

        return $next($request);
    }
}
