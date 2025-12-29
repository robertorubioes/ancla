<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure user has required permission(s).
 *
 * Usage in routes:
 * - Single permission: ->middleware('permission:documents.view')
 * - Multiple permissions (any): ->middleware('permission:documents.view,documents.create')
 */
class EnsureUserHasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  string  ...$permissions  Permission strings to check
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        // Convert permission strings to enum instances
        $permissionEnums = array_map(
            fn ($p) => Permission::from($p),
            $permissions
        );

        // Check if user has any of the required permissions
        if (! $user->hasAnyPermission($permissionEnums)) {
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
