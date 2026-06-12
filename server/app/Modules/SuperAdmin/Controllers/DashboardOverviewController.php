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
            if (!empty($businessIds) && \Illuminate\Support\Facades\Schema::hasTable('user_devices')) {
                $deviceCounts = DB::table('user_devices')
                    ->join('users', 'user_devices.user_id', '=', 'users.id')
                    ->whereIn('users.business_id', $businessIds)
                    ->where('user_devices.status', 'active')
                    ->select('users.business_id', DB::raw('count(*) as active_devices'))
                    ->groupBy('users.business_id')
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
            $expiringCount = DB::table('subscriptions')
                ->where('status', 'active')
                ->whereNotNull('current_period_end')
                ->whereBetween('current_period_end', [$now, $sevenDaysFromNow])
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
                    ->where('action', 'like', '%login failed%')
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
            if (\Illuminate\Support\Facades\Schema::hasTable('user_devices')) {
                $deviceLimitCheck = DB::select("
                    SELECT u.business_id, COUNT(d.id) as active_count, p.device_limit
                    FROM user_devices d
                    JOIN users u ON d.user_id = u.id
                    JOIN subscriptions s ON u.business_id = s.business_id
                    JOIN plans p ON s.plan_id = p.id
                    WHERE d.status = 'active' AND s.status = 'active'
                    GROUP BY u.business_id, p.device_limit
                    HAVING COUNT(d.id) >= p.device_limit
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

        // 3. KPIs
        $kpis = \Illuminate\Support\Facades\Cache::get('superadmin_dashboard_kpis');
        if (!$kpis) {
            $lock = \Illuminate\Support\Facades\Cache::lock('aggregate_superadmin_kpis_lock', 10);
            if ($lock->get()) {
                // Dispatch command to queue instead of running synchronously
                \Illuminate\Support\Facades\Artisan::queue('aggregate:superadmin-kpis');
            }
            
            $kpis = [
                'mrr' => 0,
                'total_tenants' => 0,
                'active_devices' => 0,
                'failed_logins_24h' => 0,
                'last_updated' => now()->toIso8601String()
            ];
        }

        return response()->json([
            'kpis' => $kpis,
            'recent_tenants' => $recentTenants,
            'system_alerts' => $systemAlerts
        ]);
    }
}
