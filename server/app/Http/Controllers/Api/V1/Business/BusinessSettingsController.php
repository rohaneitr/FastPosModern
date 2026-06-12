<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\UpdateBusinessSettingsRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class BusinessSettingsController extends Controller
{
    /**
     * Update business settings globally
     */
    public function update(UpdateBusinessSettingsRequest $request)
    {
        $businessId = $request->user()->business_id;

        $business = DB::table('businesses')->where('id', $businessId)->first();
        if (!$business) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        $settings = $business->settings ? json_decode($business->settings, true) : [];

        if ($request->has('pos_enforce_device_lock')) {
            $settings['pos_enforce_device_lock'] = $request->boolean('pos_enforce_device_lock');
        }

        if ($request->has('pos_enforce_strict_cash_control')) {
            $settings['pos_enforce_strict_cash_control'] = $request->boolean('pos_enforce_strict_cash_control');
        }

        DB::table('businesses')
            ->where('id', $businessId)
            ->update([
                'settings' => json_encode($settings),
                'updated_at' => now(),
            ]);

        // Purge any settings cache if we had one (future proofing)
        Cache::forget("business_settings_{$businessId}");

        return response()->json([
            'message' => 'Business settings updated successfully',
            'settings' => $settings
        ], 200);
    }
}
