<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()->unreadNotifications()->paginate(20)
        );
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All notifications marked as read']);
    }

    public function sendBulk(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'integer',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $user = $request->user();
        
        // Ensure tenant isolation
        if (!$user->hasRole('SuperAdmin')) {
            $validCount = DB::table('users')
                ->where('business_id', $user->business_id)
                ->whereIn('id', $validated['user_ids'])
                ->count();
            
            if ($validCount !== count($validated['user_ids'])) {
                return response()->json(['message' => 'Unauthorized user selection. You can only message your own staff.'], 403);
            }
        }

        \App\Jobs\SendBulkMessageJob::dispatch($validated['user_ids'], $validated['subject'], $validated['message']);

        return response()->json(['message' => 'Bulk message job dispatched to queue']);
    }
}
