<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register middleware aliases
        $middleware->alias([
            'tenant' => \App\Http\Middleware\IdentifyTenant::class,
            'identify.tenant' => \App\Http\Middleware\IdentifyTenant::class,
            'permission' => \App\Http\Middleware\EnsureUserHasPermission::class,
            'validate-session-tenant' => \App\Http\Middleware\ValidateSessionTenant::class,
            'rate.limit.public' => \App\Http\Middleware\RateLimitPublicApi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
