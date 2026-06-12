<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SubscriptionMiddleware
{
    /**
     * Enforces billing cycles and gracefully handles Grace Periods.
     */
    public function handle(Request $request, Closure $next)
    {
        $tenant = $request->attributes->get('tenant');
        
        if (!$tenant) {
            return $next($request);
        }

        if (!$tenant->billing_due_date) {
            return $next($request); // Trial or lifetime account
        }

        $dueDate = Carbon::parse($tenant->billing_due_date);
        $today = Carbon::now();

        if ($today->isAfter($dueDate)) {
            $daysPastDue = $today->diffInDays($dueDate);
            
            // 7-Day Grace Period logic
            if ($daysPastDue <= 7) {
                // Update status to Grace_Period if not already
                if ($tenant->subscription_status !== 'Grace_Period') {
                    DB::table('businesses')->where('id', $tenant->id)->update(['subscription_status' => 'Grace_Period']);
                }
                
                // Allow operational requests but attach a warning header/meta so frontend can show banner
                $response = $next($request);
                $response->headers->set('X-Subscription-Warning', 'GRACE_PERIOD');
                return $response;
            } else {
                // Hard Lock
                if ($tenant->subscription_status !== 'Locked') {
                    DB::table('businesses')->where('id', $tenant->id)->update(['subscription_status' => 'Locked']);
                }

                // If it's an API request attempting to create/update operational data
                // We allow GET requests for read-only access to their data, but block POST/PUT/DELETE
                if (!$request->isMethod('get')) {
                    return response()->json([
                        'error_code' => 'ACCOUNT_SUSPENDED',
                        'message' => 'Subscription past due. Your account is locked. Please renew to resume operations.'
                    ], 403);
                }
            }
        } elseif ($tenant->subscription_status !== 'Active') {
            // Self-healing: if they paid, restore status
            DB::table('businesses')->where('id', $tenant->id)->update(['subscription_status' => 'Active']);
        }

        return $next($request);
    }
}
