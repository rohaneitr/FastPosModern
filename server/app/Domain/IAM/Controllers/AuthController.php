<?php

namespace App\Domain\IAM\Controllers;

use App\Domain\IAM\Models\User;
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
            'subdomain' => 'nullable|string',
            'domain' => 'nullable|string',
            'remember_me' => 'nullable|boolean',
        ]);

        $isVolatile = !$request->boolean('remember_me');

        $user = User::where('username', $request->username)
                    ->orWhere('email', $request->username)
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->allow_login) {
            throw ValidationException::withMessages([
                'username' => ['This account is currently disabled.'],
            ]);
        }

        // Subdomain Check
        $user->load('business');
        $requestedDomain = $request->input('subdomain') ?? $request->input('domain');
        if ($requestedDomain && $user->business) {
            if ($user->business->subdomain !== $requestedDomain) {
                abort(403, 'This account belongs to a different workspace.');
            }
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

        // Issue token with restricted lifespan if volatile
        $expiresAt = $isVolatile ? now()->addHours(12) : null; 
        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

        // Load the associated business/tenant context
        $user->load(['business', 'roles', 'permissions']);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    /**
     * Revoke the current user's token.
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->currentAccessToken()->delete();

            // Optionally clear volatile sessions from registry on explicit logout
            DB::table('user_devices')
                ->where('user_id', $user->id)
                ->where('session_type', 'volatile')
                ->update(['status' => 'logged_out']);
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
        
        return response()->json([
            'user' => $user
        ]);
    }
}
