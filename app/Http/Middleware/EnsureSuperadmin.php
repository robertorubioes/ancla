<?php

namespace App\Http\Middleware;

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

        // Check if user has superadmin role
        if (auth()->user()->role !== 'super_admin') {
            abort(403, 'Access denied. Superadmin role required.');
        }

        return $next($request);
    }
}
