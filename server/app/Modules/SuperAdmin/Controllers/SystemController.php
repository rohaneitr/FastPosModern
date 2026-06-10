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
            // We MUST use the --secret flag, otherwise the SuperAdmin themselves will be locked out and cannot turn it off
            // via the API. The secret creates a bypass cookie when accessing `https://domain.com/superadmin-bypass`
            Artisan::call('down', [
                '--secret' => 'superadmin-bypass',
                '--render' => 'errors::503' // Or a custom view
            ]);
            
            // Optionally cache the message so the frontend can display it
            if (!empty($validated['message'])) {
                Cache::forever('maintenance_message', $validated['message']);
            }
            
            return response()->json(['message' => 'System is now in Maintenance Mode. (Bypass secret: /superadmin-bypass)']);
        } else {
            Artisan::call('up');
            Cache::forget('maintenance_message');
            
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
