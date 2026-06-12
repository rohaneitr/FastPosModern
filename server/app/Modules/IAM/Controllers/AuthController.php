<?php

namespace App\Modules\IAM\Controllers;

use App\Modules\IAM\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Authenticate a user and return a token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string', // Support username or email
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)
                    ->orWhere('email', $request->username)
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
                'errors' => ['username' => ['The provided credentials are incorrect.']]
            ], 401);
        }

        if (!$user->allow_login) {
            return response()->json([
                'message' => 'This account is currently disabled.',
                'errors' => ['username' => ['This account is currently disabled.']]
            ], 401);
        }

        // 2FA Verification
        $userData = DB::table('users')->where('id', $user->id)->first();
        if ($userData->two_factor_enabled) {
            if (!$request->has('two_factor_code')) {
                return response()->json([
                    'message' => 'Two-Factor Authentication required.',
                    'requires_2fa' => true
                ], 428); // Precondition Required
            }

            $google2fa = new Google2FA();
            $secret = decrypt($userData->two_factor_secret);
            $valid = $google2fa->verifyKey($secret, $request->input('two_factor_code'));

            // Also check recovery codes
            $usedRecoveryCode = false;
            if (!$valid && $userData->two_factor_recovery_codes) {
                $recoveryCodes = json_decode(decrypt($userData->two_factor_recovery_codes), true);
                if (in_array($request->input('two_factor_code'), $recoveryCodes)) {
                    $valid = true;
                    $usedRecoveryCode = true;
                    // Remove used code
                    $recoveryCodes = array_diff($recoveryCodes, [$request->input('two_factor_code')]);
                    DB::table('users')->where('id', $user->id)->update([
                        'two_factor_recovery_codes' => encrypt(json_encode(array_values($recoveryCodes)))
                    ]);
                }
            }

            if (!$valid) {
                throw ValidationException::withMessages([
                    'two_factor_code' => ['The provided two-factor authentication code is invalid.'],
                ]);
            }
        }

        $isVolatile = $request->boolean('volatile', false);
        // Device Registry & Tracking
        $agent = new \Jenssegers\Agent\Agent();
        $agent->setUserAgent($request->header('User-Agent'));
        $deviceOs = $agent->platform() ?: 'Unknown OS';
        $deviceBrowser = $agent->browser() ?: 'Unknown Browser';
        $deviceIp = $request->ip();
        
        $device = DB::table('user_devices')->where([
            'user_id' => $user->id,
            'os' => $deviceOs,
            'browser' => $deviceBrowser,
        ])->first();

        if ($device && $device->status === 'blocked') {
            return response()->json(['message' => 'Your device has been blocked from accessing this system.'], 403);
        }

        if (!$device) {
            DB::table('user_devices')->insert([
                'user_id' => $user->id,
                'device_name' => $agent->device() ?: 'Unknown Device',
                'os' => $deviceOs,
                'browser' => $deviceBrowser,
                'ip_address' => $deviceIp,
                'last_login' => now(),
                'status' => 'active',
                'session_type' => $isVolatile ? 'volatile' : 'persistent',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('user_devices')->where('id', $device->id)->update([
                'device_name' => $agent->device() ?: 'Unknown Device',
                'ip_address' => $deviceIp,
                'last_login' => now(),
                'session_type' => $isVolatile ? 'volatile' : 'persistent',
                'updated_at' => now(),
            ]);
        }

        // Authenticate using stateful session
        \Illuminate\Support\Facades\Auth::login($user, $request->boolean('remember_me'));
        if ($request->hasSession()) {
            $request->session()->regenerate();
            \Illuminate\Support\Facades\Log::info('Session regenerated.');
        } else {
            \Illuminate\Support\Facades\Log::info('No session found on request!');
        }

        // Issue token for mobile/API clients and legacy frontend checks
        $expiresAt = $isVolatile ? now()->addHours(12) : null; 
        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

        // Load the associated business/tenant context
        $user->load(['business', 'roles', 'permissions']);

        return response()->json([
            'message' => 'Successfully logged in',
            'user' => $user,
            'token' => $token
        ]);
    }

    /**
     * Revoke the current user's token.
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $request->user()->currentAccessToken()->delete();
            // Optionally clear volatile sessions from registry on explicit logout
            DB::table('user_devices')
                ->where('user_id', $user->id)
                ->where('session_type', 'volatile')
                ->update(['status' => 'logged_out']);
        }

        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Get the authenticated User with permissions and business context.
     */
    public function me(Request $request)
    {
        $user = $request->user()->load(['business', 'roles', 'permissions']);
        
        $responseData = ['user' => $user->toArray()];

        if ($user->business) {
            $businessId = $user->business->id;
            $activeModules = \Illuminate\Support\Facades\Cache::remember("tenant_modules:{$businessId}", 86400, function () use ($businessId) {
                return \Illuminate\Support\Facades\DB::table('tenant_modules')
                    ->join('modules', 'tenant_modules.module_id', '=', 'modules.id')
                    ->where('tenant_modules.business_id', $businessId)
                    ->where('tenant_modules.is_active', true)
                    ->where(function($query) {
                        $query->whereNull('tenant_modules.expires_at')
                              ->orWhere('tenant_modules.expires_at', '>', now());
                    })
                    ->pluck('modules.slug')
                    ->toArray();
            });

            // Overwrite the loaded business array to explicitly format the required SaaS state
            $responseData['user']['business'] = array_merge($user->business->toArray(), [
                'status' => $user->business->status,
                'active_modules' => $activeModules,
                'device_limit' => $user->business->device_limit ?? 1,
            ]);
        }

        return response()->json($responseData);
    }
}
