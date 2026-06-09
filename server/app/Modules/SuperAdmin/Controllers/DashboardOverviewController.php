<?php

namespace App\Modules\SuperAdmin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardOverviewController extends Controller
{
    public function getOverview(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { 
            abort(403, 'Unauthorized access.'); 
        }

        // 1. Recent Tenants
        $recentTenants = collect([]);
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('businesses', 'status')) {
                $recentTenants = DB::table('businesses')
                    ->select(
                        'businesses.id',
                        'businesses.name',
                        'businesses.status',
                        'businesses.created_at',
                        'plans.name as plan_name'
                    )
                    ->leftJoin('subscriptions', function ($join) {
                        $join->on('subscriptions.business_id', '=', 'businesses.id')
                             ->where('subscriptions.status', '=', 'active');
                    })
                    ->leftJoin('plans', 'subscriptions.plan_id', '=', 'plans.id')
                    ->orderBy('businesses.created_at', 'desc')
                    ->limit(5)
                    ->get();
            } else {
                $recentTenants = collect([]); // Fallback if status missing
            }

            $businessIds = $recentTenants->pluck('id')->toArray();
            $deviceCounts = [];
            if (!empty($businessIds) && \Illuminate\Support\Facades\Schema::hasTable('device_activations')) {
                $deviceCounts = DB::table('device_activations')
                    ->whereIn('business_id', $businessIds)
                    ->where('status', 'active')
                    ->select('business_id', DB::raw('count(*) as active_devices'))
                    ->groupBy('business_id')
                    ->pluck('active_devices', 'business_id')
                    ->toArray();
            }

            foreach ($recentTenants as $tenant) {
                $tenant->active_devices = $deviceCounts[$tenant->id] ?? 0;
                $tenant->created_at_formatted = Carbon::parse($tenant->created_at)->diffForHumans();
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Dashboard: Recent Tenants Failed', ['error' => $e->getMessage()]);
            $recentTenants = collect([]);
        }

        // 2. System Alerts
        $systemAlerts = [];
        $alertIdCounter = 1;

        // Pending Activations Alert
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('businesses', 'status')) {
                $pendingCount = DB::table('businesses')->where('status', 'pending_activation')->count();
                if ($pendingCount > 0) {
                    $systemAlerts[] = [
                        'id' => $alertIdCounter++,
                        'type' => 'warning',
                        'title' => 'Pending Activations',
                        'message' => "{$pendingCount} tenant(s) are awaiting license activation.",
                        'time' => 'Just now'
                    ];
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Dashboard: Pending Activations Failed', ['error' => $e->getMessage()]);
        }

        // Expiring Licenses Alert
        try {
            $sevenDaysFromNow = Carbon::now()->addDays(7)->toDateTimeString();
            $now = Carbon::now()->toDateTimeString();
            $expiringCount = DB::table('licenses')
                ->where('status', 'active')
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [$now, $sevenDaysFromNow])
                ->count();
            if ($expiringCount > 0) {
                $systemAlerts[] = [
                    'id' => $alertIdCounter++,
                    'type' => 'danger',
                    'title' => 'Licenses Expiring Soon',
                    'message' => "{$expiringCount} active license(s) are expiring within the next 7 days.",
                    'time' => 'Just now'
                ];
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Dashboard: Expiring Licenses Failed', ['error' => $e->getMessage()]);
        }

        // Security/Login Alert
        try {
            $yesterday = Carbon::now()->subDay()->toDateTimeString();
            $failedLogins = 0;
            if (\Illuminate\Support\Facades\Schema::hasTable('audit_logs')) {
                $failedLogins = DB::table('audit_logs')
                    ->where('event', 'like', '%login_failed%')
                    ->where('created_at', '>=', $yesterday)
                    ->count();
            }
            if ($failedLogins === 0 && \Illuminate\Support\Facades\Schema::hasTable('user_activities')) {
                $failedLogins = DB::table('user_activities')
                    ->where('activity', 'like', '%login failed%')
                    ->where('created_at', '>=', $yesterday)
                    ->count();
            }
            
            if ($failedLogins > 5) {
                $systemAlerts[] = [
                    'id' => $alertIdCounter++,
                    'type' => 'danger',
                    'title' => 'Security Alert',
                    'message' => "{$failedLogins} failed login attempts detected in the last 24 hours.",
                    'time' => 'Just now'
                ];
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Dashboard: Security Alert Failed', ['error' => $e->getMessage()]);
        }

        // Device Limit Alert
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('device_activations')) {
                $deviceLimitCheck = DB::select("
                    SELECT d.business_id, COUNT(d.id) as active_count, l.device_limit
                    FROM device_activations d
                    JOIN licenses l ON d.license_key = l.license_key
                    WHERE d.status = 'active' AND l.status = 'active'
                    GROUP BY d.business_id, l.device_limit
                    HAVING COUNT(d.id) >= l.device_limit
                ");
                
                if (count($deviceLimitCheck) > 0) {
                    $systemAlerts[] = [
                        'id' => $alertIdCounter++,
                        'type' => 'warning',
                        'title' => 'Device Quota Reached',
                        'message' => count($deviceLimitCheck) . " tenant(s) have reached their maximum device limit.",
                        'time' => 'Just now'
                    ];
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Dashboard: Device Limit Alert Failed', ['error' => $e->getMessage()]);
        }

        if (empty($systemAlerts)) {
            $systemAlerts[] = [
                'id' => $alertIdCounter++,
                'type' => 'success',
                'title' => 'System Healthy',
                'message' => 'All systems operational. No critical alerts.',
                'time' => 'Just now'
            ];
        }

        return response()->json([
            'recent_tenants' => $recentTenants,
            'system_alerts' => $systemAlerts
        ]);
    }
}
