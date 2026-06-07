<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends Controller
{
    public function createGlobal(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|string|in:info,warning,success',
            'expires_at' => 'nullable|date',
        ]);

        $validated['business_id'] = null;
        $validated['type'] = $validated['type'] ?? 'info';
        $validated['created_at'] = now();
        $validated['updated_at'] = now();

        DB::table('announcements')->insert($validated);

        return response()->json(['message' => 'Global announcement created successfully'], 201);
    }

    public function createTenant(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|string|in:info,warning,success',
            'expires_at' => 'nullable|date',
        ]);

        $validated['business_id'] = $request->user()->business_id;
        $validated['type'] = $validated['type'] ?? 'info';
        $validated['created_at'] = now();
        $validated['updated_at'] = now();

        DB::table('announcements')->insert($validated);

        return response()->json(['message' => 'Tenant announcement created successfully'], 201);
    }

    public function index(Request $request)
    {
        $businessId = $request->user()->business_id;

        $query = DB::table('announcements')
            ->where(function ($q) use ($businessId) {
                $q->whereNull('business_id');
                if ($businessId) {
                    $q->orWhere('business_id', $businessId);
                }
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($query);
    }
}
