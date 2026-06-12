<?php

namespace App\Modules\IAM\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Validation\Rules\Password;

class PasswordResetController extends Controller
{
    /**
     * Request a password reset token.
     * In a production app this would send an email. For now,
     * it returns the token directly (suitable for SPA/mobile flows).
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $token = Str::random(64);

        // Delete any existing reset tokens for this email
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        DB::table('password_reset_tokens')->insert([
            'email' => $request->email,
            'token' => Hash::make($token),
            'created_at' => Carbon::now(),
        ]);

        // Send email notification
        try {
            \Illuminate\Support\Facades\Mail::to($request->email)
                ->send(new \App\Mail\PasswordResetMail($token));
        } catch (\Exception $e) {
            // Silently fail in dev if no mail driver configured
            \Illuminate\Support\Facades\Log::warning('Password reset email failed: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Password reset instructions have been sent to your email.',
            'token' => app()->isLocal() ? $token : null, // Only expose token in local/dev
        ]);
    }

    /**
     * Reset password using the token.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record) {
            return response()->json(['message' => 'No reset token found for this email.'], 422);
        }

        // Check token expiry (1 hour)
        if (Carbon::parse($record->created_at)->addHour()->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['message' => 'Reset token has expired. Please request a new one.'], 422);
        }

        if (!Hash::check($request->token, $record->token)) {
            return response()->json(['message' => 'Invalid reset token.'], 422);
        }

        // Update the user's password
        DB::table('users')
            ->where('email', $request->email)
            ->update([
                'password' => Hash::make($request->password),
                'updated_at' => Carbon::now(),
            ]);

        // Clean up the token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password has been reset successfully. You can now log in.']);
    }
}
