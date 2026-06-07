<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Domain\Tenant\Models\Business;

class BusinessSettingsController extends Controller
{
    /**
     * Update Tenant White-labeling / Branding Logos
     */
    public function updateBranding(Request $request)
    {
        $business = Business::findOrFail($request->user()->business_id);

        $request->validate([
            'dashboard_logo' => 'nullable|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
            'invoice_logo' => 'nullable|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
        ]);

        $updateData = [];

        if ($request->hasFile('dashboard_logo')) {
            if ($business->dashboard_logo) {
                Storage::disk('public')->delete($business->dashboard_logo);
            }
            $path = $request->file('dashboard_logo')->store("uploads/tenant_logos/{$business->id}", 'public');
            $updateData['dashboard_logo'] = $path;
        }

        if ($request->hasFile('invoice_logo')) {
            if ($business->invoice_logo) {
                Storage::disk('public')->delete($business->invoice_logo);
            }
            $path = $request->file('invoice_logo')->store("uploads/tenant_logos/{$business->id}", 'public');
            $updateData['invoice_logo'] = $path;
        }

        if (!empty($updateData)) {
            $business->update($updateData);
        }

        return response()->json([
            'message' => 'Tenant branding updated successfully.',
            'dashboard_logo_url' => $business->dashboard_logo ? asset('storage/' . $business->dashboard_logo) : null,
            'invoice_logo_url' => $business->invoice_logo ? asset('storage/' . $business->invoice_logo) : null,
        ]);
    }
}
