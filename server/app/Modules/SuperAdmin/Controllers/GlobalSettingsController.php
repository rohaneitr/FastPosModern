<?php

namespace App\Modules\SuperAdmin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SuperAdmin\Models\GlobalSetting;
use App\Modules\SuperAdmin\Requests\UpdateGlobalSettingsRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

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

        $disk = env('FILESYSTEM_DISK', 'public');

        if ($request->hasFile('saas_logo')) {
            $path = $request->file('saas_logo')->store('branding', $disk);
            $this->saveSetting('saas_logo', $path, 'branding');
            $this->saveSetting('saas_logo_disk', $disk, 'branding');
        }

        // Feature Flags
        if (isset($validated['enable_registration'])) {
            $this->saveSetting('enable_registration', $validated['enable_registration'], 'system');
        }

        if (isset($validated['maintenance_mode'])) {
            $this->saveSetting('maintenance_mode', $validated['maintenance_mode'], 'system');
            
            if ($validated['maintenance_mode']) {
                Artisan::call('down', [
                    '--secret' => 'superadmin-bypass',
                    '--render' => 'errors::503'
                ]);
            } else {
                Artisan::call('up');
            }
        }

        if ($request->hasFile('favicon')) {
            $path = $request->file('favicon')->store('branding', $disk);
            $this->saveSetting('favicon', $path, 'branding');
            $this->saveSetting('favicon_disk', $disk, 'branding');
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

            // Phase 1: Dynamic S3 Architecture (URL Resolver)
            if (isset($mapped['saas_logo']) && !str_starts_with($mapped['saas_logo'], 'http') && !str_starts_with($mapped['saas_logo'], '/storage/')) {
                $disk = $mapped['saas_logo_disk'] ?? 'public';
                $mapped['saas_logo'] = \Illuminate\Support\Facades\Storage::disk($disk)->url($mapped['saas_logo']);
            }

            if (isset($mapped['favicon']) && !str_starts_with($mapped['favicon'], 'http') && !str_starts_with($mapped['favicon'], '/storage/')) {
                $disk = $mapped['favicon_disk'] ?? 'public';
                $mapped['favicon'] = \Illuminate\Support\Facades\Storage::disk($disk)->url($mapped['favicon']);
            }

            return $mapped;
        });
    }

    /**
     * Phase 2: SMTP Handshake Verification (With Timeout Lock)
     */
    public function testSmtp(Request $request)
    {
        $request->validate([
            'smtp_host' => 'required|string',
            'smtp_port' => 'required|numeric',
            'smtp_username' => 'nullable|string',
            'smtp_password' => 'nullable|string',
            'smtp_encryption' => 'nullable|in:tls,ssl,'
        ]);

        try {
            $host = $request->smtp_host;
            $port = $request->smtp_port;
            $enc = $request->smtp_encryption === 'ssl' ? 'smtps' : 'smtp';
            
            // Build DSN
            $dsnString = "{$enc}://";
            if ($request->smtp_username) {
                $dsnString .= rawurlencode($request->smtp_username);
                if ($request->smtp_password && $request->smtp_password !== '********') {
                    $dsnString .= ':' . rawurlencode($request->smtp_password);
                } elseif ($request->smtp_password === '********') {
                    // Fetch real password from DB if they submitted the mask
                    $setting = GlobalSetting::where('key', 'smtp_password')->first();
                    if ($setting) {
                        $dsnString .= ':' . rawurlencode($setting->value);
                    }
                }
                $dsnString .= '@';
            }
            $dsnString .= "{$host}:{$port}";

            $dsn = \Symfony\Component\Mailer\Transport\Dsn::fromString($dsnString);
            $factory = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory();
            $transport = $factory->create($dsn);

            // Access underlying stream and set strict 5-second timeout
            if (method_exists($transport, 'getStream')) {
                $stream = $transport->getStream();
                if (method_exists($stream, 'setTimeout')) {
                    $stream->setTimeout(5.0);
                }
            }

            // Test Handshake (Timeout will trigger here)
            $transport->start();

            return response()->json(['message' => 'SMTP Handshake Successful. Connection verified.']);

        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            $msg = $e->getMessage();
            if (str_contains(strtolower($msg), 'timed out') || str_contains(strtolower($msg), 'timeout')) {
                return response()->json(['message' => 'Connection timed out. Port may be blocked by firewall.'], 408);
            }
            return response()->json(['message' => 'SMTP Handshake Failed: ' . $msg], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Configuration Error: ' . $e->getMessage()], 422);
        }
    }
}
