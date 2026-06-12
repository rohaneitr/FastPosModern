<?php

namespace App\Modules\SuperAdmin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class SystemController extends Controller
{
    public function toggleMaintenance(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'message' => 'nullable|string|max:500'
        ]);

        if ($validated['enabled']) {
            Cache::forever('maintenance_mode_enabled', true);
            Cache::forever('maintenance_message', $validated['message'] ?? 'System under maintenance.');

            \App\Modules\Tenant\Services\AuditLogger::log(
                0, // 0 for SuperAdmin system level
                $request->user(),
                'maintenance_mode_enabled',
                'System',
                0,
                [],
                ['message' => 'System placed in Maintenance Mode (API Lockout) by SuperAdmin.']
            );
            
            return response()->json([
                'message' => 'System is now in Maintenance Mode. Tenant APIs are locked, SuperAdmin bypass active.'
            ]);
        } else {
            Cache::forget('maintenance_mode_enabled');
            Cache::forget('maintenance_message');

            \App\Modules\Tenant\Services\AuditLogger::log(
                0,
                $request->user(),
                'maintenance_mode_disabled',
                'System',
                0,
                [],
                ['message' => 'System Maintenance Mode disabled by SuperAdmin.']
            );
            
            return response()->json(['message' => 'System is now Online.']);
        }
    }

    public function broadcastAnnouncement(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|in:info,warning,danger',
            'expires_at' => 'nullable|date'
        ]);

        $announcement = array_merge($validated, [
            'id' => uniqid('ann_'),
            'created_at' => now()->toIso8601String()
        ]);

        // Storing the latest announcement in Cache for high-performance O(1) global read
        Cache::put('global_announcement', $announcement, isset($validated['expires_at']) ? \Carbon\Carbon::parse($validated['expires_at']) : now()->addDays(7));

        // Optionally, broadcast via Reverb/Pusher if we want it instantly
        // broadcast(new \App\Events\GlobalAnnouncementEvent($announcement));

        return response()->json([
            'message' => 'Announcement broadcasted successfully',
            'announcement' => $announcement
        ]);
    }

    public function getAnnouncements()
    {
        // Public API endpoint (no auth required) so Next.js can fetch it on _app load
        $announcement = Cache::get('global_announcement');
        
        return response()->json([
            'active' => $announcement ? [$announcement] : []
        ]);
    }
}
