<?php

namespace App\Modules\SuperAdmin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SuperAdmin\Models\GlobalSetting;
use App\Modules\SuperAdmin\Requests\UpdateGlobalSettingsRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GlobalSettingsController extends Controller
{
    private const CACHE_KEY = 'global_settings_cache';

    /**
     * Get all global settings (Cached)
     */
    public function index(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) {
            abort(403, 'Unauthorized access.');
        }

        $settings = $this->getCachedSettings();

        // For security, do not return SMTP passwords to the frontend, even to SuperAdmin
        if (isset($settings['smtp_password'])) {
            $settings['smtp_password'] = '********'; // Masked
        }

        return response()->json($settings);
    }

    /**
     * Update global settings and clear cache
     */
    public function update(UpdateGlobalSettingsRequest $request)
    {
        $validated = $request->validated();

        $this->saveSetting('saas_name', $validated['saas_name'] ?? null, 'branding');
        $this->saveSetting('timezone', $validated['timezone'] ?? null, 'system');
        $this->saveSetting('default_currency_symbol', $validated['default_currency_symbol'] ?? null, 'system');
        $this->saveSetting('smtp_sender_address', $validated['smtp_sender_address'] ?? null, 'smtp');

        if (isset($validated['smtp_host'])) $this->saveSetting('smtp_host', $validated['smtp_host'], 'smtp');
        if (isset($validated['smtp_port'])) $this->saveSetting('smtp_port', $validated['smtp_port'], 'smtp');
        if (isset($validated['smtp_username'])) $this->saveSetting('smtp_username', $validated['smtp_username'], 'smtp');
        if (isset($validated['smtp_encryption'])) $this->saveSetting('smtp_encryption', $validated['smtp_encryption'], 'smtp');
        
        // Handle password specifically to avoid saving "********"
        if (isset($validated['smtp_password']) && $validated['smtp_password'] !== '********' && $validated['smtp_password'] !== '') {
            $this->saveSetting('smtp_password', $validated['smtp_password'], 'smtp', true);
        }

        // Handle File Uploads
        if ($request->hasFile('saas_logo')) {
            $path = $request->file('saas_logo')->store('branding', 'public');
            $this->saveSetting('saas_logo', '/storage/' . $path, 'branding');
        }

        if ($request->hasFile('favicon')) {
            $path = $request->file('favicon')->store('branding', 'public');
            $this->saveSetting('favicon', '/storage/' . $path, 'branding');
        }

        // Purge Cache
        Cache::forget(self::CACHE_KEY);

        return response()->json([
            'message' => 'Global settings updated successfully.',
            'settings' => $this->getCachedSettings()
        ]);
    }

    /**
     * Helper to save a setting
     */
    private function saveSetting($key, $value, $group, $encrypt = false)
    {
        if ($value === null) return;

        $setting = GlobalSetting::firstOrNew(['key' => $key]);
        $setting->is_encrypted = $encrypt;
        $setting->group = $group;
        $setting->value = $value; // Mutator handles encryption
        $setting->save();
    }

    /**
     * Helper to retrieve and cache settings
     */
    private function getCachedSettings()
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $settings = GlobalSetting::all();
            $mapped = [];
            foreach ($settings as $setting) {
                $mapped[$setting->key] = $setting->value; // Accessor handles decryption
            }
            return $mapped;
        });
    }
}
