<?php

namespace App\Domain\IAM\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Collection;

class TwoFactorController extends Controller
{
    /**
     * Generate 2FA Secret and Setup QR Code Data
     */
    public function enable(Request $request)
    {
        $user = $request->user();
        
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
        
        // Generate recovery codes
        $recoveryCodes = Collection::times(8, function () {
            return \Illuminate\Support\Str::random(10);
        })->toArray();

        // Save unconfirmed 2FA setup to DB
        DB::table('users')->where('id', $user->id)->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ]);

        $qrCodeSvg = $google2fa->getQRCodeInline(
            config('app.name', 'FastPOS'),
            $user->email ?? $user->username,
            $secret
        );

        return response()->json([
            'secret' => $secret,
            'qr_code_svg' => $qrCodeSvg,
            'recovery_codes' => $recoveryCodes
        ]);
    }

    /**
     * Verify 2FA setup with OTP code
     */
    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        
        $user = $request->user();
        
        // Use DB directly or reload user
        $userData = DB::table('users')->where('id', $user->id)->first();
        
        if (!$userData->two_factor_secret) {
            return response()->json(['message' => '2FA is not initiated'], 400);
        }

        $secret = decrypt($userData->two_factor_secret);
        
        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($secret, $request->code);

        if ($valid) {
            DB::table('users')->where('id', $user->id)->update([
                'two_factor_enabled' => true,
                'two_factor_confirmed_at' => now(),
            ]);

            return response()->json(['message' => 'Two-Factor Authentication enabled successfully.']);
        }

        return response()->json(['message' => 'Invalid OTP Code'], 422);
    }

    /**
     * Disable 2FA
     */
    public function disable(Request $request)
    {
        $request->validate(['password' => 'required|current_password']);

        DB::table('users')->where('id', $request->user()->id)->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json(['message' => 'Two-Factor Authentication disabled.']);
    }
}
