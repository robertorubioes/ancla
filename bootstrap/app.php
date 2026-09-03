<?php

use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\RateLimitPublicApi;
use App\Http\Middleware\ValidateSessionTenant;
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
            'tenant' => IdentifyTenant::class,
            'identify.tenant' => IdentifyTenant::class,
            'permission' => EnsureUserHasPermission::class,
            'validate-session-tenant' => ValidateSessionTenant::class,
            'rate.limit.public' => RateLimitPublicApi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
