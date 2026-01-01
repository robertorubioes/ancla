<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperadmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (! auth()->check()) {
            abort(401, 'Unauthenticated');
        }

        $user = auth()->user();
        $role = $user->role;
        
        // Handle both enum and string roles
        $roleValue = $role instanceof UserRole ? $role->value : $role;
        
        // Check if user has superadmin role
        if ($roleValue !== UserRole::SUPER_ADMIN->value) {
            abort(403, 'Access denied. Superadmin role required.');
        }

        return $next($request);
    }
}
