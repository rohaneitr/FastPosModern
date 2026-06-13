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
            'rbac.shadow' => \App\Http\Middleware\RbacShadowLogger::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null; // Let default handler deal with non-API requests
            }

            // ── 422 Validation ─────────────────────────────────────────────────────
            // Do NOT override — Laravel formats field errors for React Hook Form /
            // Axios interceptors. We only standardize the top-level envelope shape.
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'The given data was invalid.',
                    'code'    => 'VALIDATION_FAILED',
                    'errors'  => $e->errors(),
                ], 422);
            }

            // ── Resolve status code & machine-readable code ────────────────────────
            $status    = 500;
            $errorCode = 'INTERNAL_SERVER_ERROR';
            $message   = 'An unexpected error occurred.';

            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                // 401 — unauthenticated (e.g. expired/missing Sanctum session)
                $status    = 401;
                $errorCode = 'UNAUTHENTICATED';
                $message   = 'Unauthenticated. Please log in.';

            } elseif (
                $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || $e instanceof \Spatie\Permission\Exceptions\UnauthorizedException
                || $e instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
            ) {
                // 403 — authenticated but forbidden
                $status    = 403;
                $errorCode = 'FORBIDDEN';
                $message   = 'You do not have permission to perform this action.';

            } elseif (
                $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                || $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
            ) {
                // 404 — resource not found
                $status    = 404;
                $errorCode = 'NOT_FOUND';
                $message   = 'The requested resource was not found.';

            } elseif ($e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
                // 429 — rate limit exceeded
                $status    = 429;
                $errorCode = 'RATE_LIMIT_EXCEEDED';
                $message   = 'Too many requests. Please slow down and try again.';

            } elseif ($e instanceof \Illuminate\Database\QueryException) {
                if ($e->getCode() === '40001') {
                    // 409 — serialization / deadlock
                    $status    = 409;
                    $errorCode = 'CONCURRENCY_DEADLOCK';
                    $message   = 'Transaction deadlock detected. Please retry.';
                } else {
                    // 500 — generic DB error (details shielded from client)
                    $status    = 500;
                    $errorCode = 'DATABASE_ERROR';
                    $message   = 'A database error occurred.';
                }
            } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                // Catch-all for remaining HttpExceptions (405, 413, etc.)
                $status    = $e->getStatusCode();
                $errorCode = 'HTTP_ERROR';
                $message   = $e->getMessage() ?: 'An HTTP error occurred.';
            } else {
                // Generic 500 — hide internal details from client
                $message = config('app.debug')
                    ? $e->getMessage()
                    : 'An unexpected error occurred.';
            }

            // ── Build the unified ApiResponse envelope ─────────────────────────────
            $payload = [
                'success' => false,
                'message' => $message,
                'code'    => $errorCode,
            ];

            // Append debug trace in non-production environments
            if (! app()->environment('production') && config('app.debug')) {
                $payload['debug'] = [
                    'exception' => get_class($e),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                    'trace'     => array_slice($e->getTrace(), 0, 10),
                ];
            }

            return response()->json($payload, $status);
        });
    })->create();
