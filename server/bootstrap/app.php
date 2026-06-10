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
            '*',
        ]);

        $middleware->append(\App\Http\Middleware\EnsureLicenseIsActive::class);
        $middleware->api(prepend: [
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
        ], append: [
            \App\Http\Middleware\IdleTimeoutMiddleware::class,
            \App\Http\Middleware\GlobalSecuritySanitizer::class,
        ]);
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'subscribed' => \App\Http\Middleware\CheckSubscription::class,
            'module' => \App\Http\Middleware\EnforceTenantModuleAccess::class,
            'module.access' => \App\Http\Middleware\CheckModuleAccess::class,
            'entitlement' => \App\Http\Middleware\EntitlementMiddleware::class,
            'hardware_lock' => \App\Http\Middleware\VerifyHardwareHash::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                
                // Do not override Laravel's default ValidationException structure 
                // to maintain compatibility with React Hook Form / Axios interceptors
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return null; // Let Laravel handle it
                }

                $status = 500;
                $errorCode = 'INTERNAL_SERVER_ERROR';
                $message = $e->getMessage() ?: 'An unexpected error occurred.';

                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException || $e instanceof \Spatie\Permission\Exceptions\UnauthorizedException) {
                    $status = 403;
                    $errorCode = 'UNAUTHORIZED_ACCESS';
                } elseif ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException || $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    $status = 404;
                    $errorCode = 'NOT_FOUND';
                    $message = 'The requested resource was not found.';
                } elseif ($e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
                    $status = 429;
                    $errorCode = 'RATE_LIMIT_EXCEEDED';
                    $message = 'Too many requests. Please slow down and try again.';
                } elseif ($e instanceof \Illuminate\Database\QueryException) {
                    if ($e->getCode() === '40001') {
                        $status = 409;
                        $errorCode = 'CONCURRENCY_DEADLOCK';
                        $message = 'Transaction deadlock detected. Please retry.';
                    } else {
                        // Shield generic DB errors
                        $message = 'A database error occurred.';
                    }
                }

                if (method_exists($e, 'getStatusCode')) {
                    $status = $e->getStatusCode();
                }

                $payload = [
                    'error' => true,
                    'code' => $errorCode,
                    'message' => $message,
                ];

                if (!app()->environment('production') && config('app.debug')) {
                    $payload['debug'] = [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => array_slice($e->getTrace(), 0, 10),
                    ];
                }

                return response()->json($payload, $status);
            }
        });
    })->create();
