<?php

namespace App\Domain\IAM\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;

class PasswordResetController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $email = strtolower(trim($request->email));
        $userExists = DB::table('users')->where('email', $email)->exists();

        if ($userExists) {
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            DB::table('password_reset_tokens')->where('email', $email)->delete();

            DB::table('password_reset_tokens')->insert([
                'email'      => $email,
                'token'      => Hash::make($otp),
                'created_at' => Carbon::now(),
            ]);

            try {
                Mail::raw("Your password reset OTP is: {$otp}. It expires in 15 minutes.", function (Message $message) use ($email) {
                    $message->to($email)
                            ->subject('Password Reset OTP');
                });
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Password reset email failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'If that email address is registered, you will receive a password reset OTP shortly.',
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'otp'   => 'required|string|size:6',
        ]);

        $email = strtolower(trim($request->email));

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$record || !Hash::check($request->otp, $record->token)) {
            return response()->json([
                'message' => 'This OTP is invalid.',
            ], 422);
        }

        if (Carbon::parse($record->created_at)->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return response()->json([
                'message' => 'This OTP has expired. Please request a new one.',
            ], 422);
        }

        $tempToken = Str::random(64);
        
        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->update([
                'token' => Hash::make($tempToken),
                'created_at' => Carbon::now(),
            ]);

        return response()->json([
            'message' => 'OTP verified successfully.',
            'temp_token' => $tempToken,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'                 => 'required|email|max:255',
            'token'                 => 'required|string|min:64',
            'password'              => ['required', 'confirmed', Password::defaults()],
        ]);

        $email = strtolower(trim($request->email));

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return response()->json([
                'message' => 'This temporary token is invalid or has already been used.',
            ], 422);
        }

        if (Carbon::parse($record->created_at)->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return response()->json([
                'message' => 'This temporary token has expired. Please start over.',
            ], 422);
        }

        DB::transaction(function () use ($email, $request) {
            $user = DB::table('users')->where('email', $email)->first();

            DB::table('users')
                ->where('email', $email)
                ->update([
                    'password'   => Hash::make($request->password),
                    'updated_at' => Carbon::now(),
                ]);

            if ($user) {
                DB::table('personal_access_tokens')
                    ->where('tokenable_id', $user->id)
                    ->where('tokenable_type', \App\Domain\IAM\Models\User::class)
                    ->delete();
            }

            DB::table('password_reset_tokens')->where('email', $email)->delete();
        });

        return response()->json([
            'message' => 'Your password has been reset successfully. Please sign in with your new password.',
        ]);
    }
}
