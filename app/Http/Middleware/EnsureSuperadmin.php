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
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (! auth()->check()) {
            abort(401, 'Unauthenticated');
        }

        $user = auth()->user();

        // Check if user has superadmin role
        if ($user->role !== UserRole::SUPER_ADMIN) {
            abort(403, 'Access denied. Superadmin role required.');
        }

        return $next($request);
    }
}
