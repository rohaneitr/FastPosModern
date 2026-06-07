<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SuperAdminTelemetryController extends Controller
{
    public function monitoring()
    {
        return response()->json([
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'cpu_usage' => '12%',
            'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
            'active_tenants' => DB::table('businesses')->where('is_active', true)->count()
        ]);
    }

    public function auditLogs()
    {
        if (Schema::hasTable('audit_logs')) {
            $logs = DB::table('audit_logs')->orderBy('created_at', 'desc')->limit(50)->get();
            return response()->json(['data' => $logs]);
        }
        
        return response()->json(['data' => []]);
    }

    public function emailLogs()
    {
        if (Schema::hasTable('email_logs')) {
            $logs = DB::table('email_logs')->orderBy('created_at', 'desc')->limit(50)->get();
            return response()->json(['data' => $logs]);
        }
        
        return response()->json(['data' => []]);
    }

    public function licenses()
    {
        if (Schema::hasTable('licenses')) {
            $licenses = DB::table('licenses')->orderBy('created_at', 'desc')->get();
            return response()->json(['data' => $licenses]);
        } elseif (Schema::hasTable('device_activations')) {
            $activations = DB::table('device_activations')->orderBy('created_at', 'desc')->get();
            return response()->json(['data' => $activations]);
        }
        
        return response()->json(['data' => []]);
    }

    public function approvals()
    {
        if (Schema::hasTable('tenant_requests')) {
            $requests = DB::table('tenant_requests')->where('status', 'pending')->get();
            return response()->json(['data' => $requests]);
        } elseif (Schema::hasTable('subscription_requests')) {
            $requests = DB::table('subscription_requests')->where('status', 'pending')->get();
            return response()->json(['data' => $requests]);
        }
        
        return response()->json(['data' => []]);
    }
}
