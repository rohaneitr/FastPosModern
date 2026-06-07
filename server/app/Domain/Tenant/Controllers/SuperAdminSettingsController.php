<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class SuperAdminSettingsController extends Controller
{
    /**
     * Update Global SaaS White-labeling / Branding
     */
    public function updateBranding(Request $request)
    {
        $request->validate([
            'saas_name' => 'nullable|string|max:255',
            'saas_logo' => 'nullable|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
            'favicon' => 'nullable|image|mimes:jpeg,png,jpg,webp,svg,ico|max:1024',
        ]);

        $updateSetting = function ($key, $value) {
            DB::table('global_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        };

        if ($request->has('saas_name')) {
            $updateSetting('saas_name', $request->saas_name);
        }

        if ($request->hasFile('saas_logo')) {
            $old = DB::table('global_settings')->where('key', 'saas_logo')->first();
            if ($old && $old->value) {
                Storage::disk('public')->delete($old->value);
            }
            $path = $request->file('saas_logo')->store('uploads/global_branding', 'public');
            $updateSetting('saas_logo', $path);
        }

        if ($request->hasFile('favicon')) {
            $old = DB::table('global_settings')->where('key', 'saas_favicon')->first();
            if ($old && $old->value) {
                Storage::disk('public')->delete($old->value);
            }
            $path = $request->file('favicon')->store('uploads/global_branding', 'public');
            $updateSetting('saas_favicon', $path);
        }

        return response()->json([
            'message' => 'Global SaaS branding updated successfully.'
        ]);
    }
}
