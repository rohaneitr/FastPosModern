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
        ]);

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

        // Issue token
        $token = $user->createToken('auth_token')->plainTextToken;

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
        $request->user()->currentAccessToken()->delete();

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
