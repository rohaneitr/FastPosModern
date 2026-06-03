<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperadminController extends Controller
{
    /**
     * Get all businesses/tenants for the SaaS dashboard.
     * Note: In a real app, this must be restricted to Superadmins only!
     */
    public function businesses(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { 
            abort(403, 'Unauthorized access.'); 
        }

        $query = DB::table('businesses')
            ->join('users', 'businesses.owner_id', '=', 'users.id')
            ->select(
                'businesses.id',
                'businesses.name as business_name',
                DB::raw("users.first_name || ' ' || users.last_name as owner_name"),
                'users.email as owner_email',
                'businesses.is_active',
                'businesses.subscription_expires_at',
                'businesses.created_at'
            );

        if ($request->has('search') && $request->search != '') {
            $search = strtolower($request->search);
            $query->where(function($q) use ($search) {
                $q->where(DB::raw('LOWER(businesses.name)'), 'like', "%{$search}%")
                  ->orWhere(DB::raw("LOWER(users.first_name || ' ' || users.last_name)"), 'like', "%{$search}%")
                  ->orWhere(DB::raw('LOWER(users.email)'), 'like', "%{$search}%");
            });
        }

        $businesses = $query->orderBy('businesses.created_at', 'desc')->paginate(20);

        return response()->json($businesses);
    }

    /**
     * Toggle business active status (Suspend/Unsuspend)
     */
    public function toggleStatus(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { 
            abort(403, 'Unauthorized access.'); 
        }
        $business = DB::table('businesses')->where('id', $id)->first();
        if (!$business) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        DB::table('businesses')
            ->where('id', $id)
            ->update(['is_active' => !$business->is_active]);

        return response()->json(['message' => 'Business status updated', 'is_active' => !$business->is_active]);
    }
}
