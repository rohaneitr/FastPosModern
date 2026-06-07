<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'api/v1/payments/*/callback',
            'api/v1/webhooks/*',
            'api/v1/devices/heartbeat',
            'api/v1/devices/status'
        ]);

        $middleware->append(\App\Http\Middleware\ActivityLogger::class);
        $middleware->append(\App\Http\Middleware\SaaSMaintenanceMode::class);
        $middleware->append(\App\Http\Middleware\EnsureLicenseIsActive::class);
        $middleware->api(append: [
            \App\Http\Middleware\IdleTimeoutMiddleware::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'subscribed' => \App\Http\Middleware\CheckSubscription::class,
            'module' => \App\Http\Middleware\CheckModuleAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
