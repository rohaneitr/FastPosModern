<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActivityLogger
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log sensitive actions
        if ($this->isSensitiveAction($request)) {
            try {
                $user = $request->user();
                if ($user) {
                    DB::table('audit_logs')->insert([
                        'causer_id' => $user->id,
                        'causer_type' => get_class($user),
                        'causer_name' => $user->name ?? $user->email,
                        'event' => $this->getEventName($request),
                        'description' => $this->getDescription($request),
                        'properties' => json_encode($request->all()),
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'created_at' => now()
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to write audit log: ' . $e->getMessage());
            }
        }

        return $response;
    }

    private function isSensitiveAction(Request $request): bool
    {
        if ($request->isMethod('GET')) {
            return false;
        }

        $path = $request->path();

        // Sensitive paths
        $sensitivePaths = [
            'api/v1/register/close',
            'api/v1/transactions', // creating, deleting transactions
            'api/v1/users', // creating, modifying users
            'api/v1/purchases/return',
            'api/v1/sales/return'
        ];

        foreach ($sensitivePaths as $sp) {
            if (str_contains($path, $sp)) {
                return true;
            }
        }

        return false;
    }

    private function getEventName(Request $request): string
    {
        $method = $request->method();
        $path = $request->path();

        if (str_contains($path, 'register/close')) return 'register_closed';
        if (str_contains($path, 'purchases/return')) return 'purchase_returned';
        if (str_contains($path, 'sales/return')) return 'sale_returned';
        
        if ($method === 'DELETE') return 'resource_deleted';
        if ($method === 'POST') return 'resource_created';
        if ($method === 'PUT' || $method === 'PATCH') return 'resource_updated';
        
        return 'sensitive_action';
    }

    private function getDescription(Request $request): string
    {
        return "Performed {$request->method()} on {$request->path()}";
    }
}
