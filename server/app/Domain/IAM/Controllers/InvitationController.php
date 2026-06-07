<?php

namespace App\Domain\IAM\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;
use App\Domain\IAM\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\DB;

class InvitationController extends Controller
{
    /**
     * Send an invitation to a new staff member.
     * Accessible only by BusinessAdmin.
     */
    public function sendInvite(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'role' => 'required|string|exists:roles,name'
        ]);

        $inviter = $request->user();
        $business_id = $inviter->business_id;

        if (!$inviter->hasRole('BusinessAdmin') || !$business_id) {
            abort(403, 'Unauthorized. Only Business Admins can send invitations.');
        }

        if (User::where('email', $request->email)->exists()) {
            return response()->json(['message' => 'User with this email already exists.'], 422);
        }

        // Generate a cryptographically signed URL valid for 48 hours
        $signedUrl = URL::temporarySignedRoute(
            'api.invites.accept',
            now()->addHours(48),
            [
                'business_id' => $business_id,
                'role' => $request->role,
                'email' => $request->email
            ]
        );

        // Map the signed API URL to a Frontend URL
        $queryString = parse_url($signedUrl, PHP_URL_QUERY);
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000') 
                       . '/accept-invite?' 
                       . $queryString;

        // Send the invitation email
        try {
            Mail::raw("You have been invited to join the team as a {$request->role}. Click here to accept the invitation and set up your account: {$frontendUrl} \n\nThis link expires in 48 hours.", function (Message $message) use ($request) {
                $message->to($request->email)
                        ->subject('Invitation to join FastPOS');
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Invitation email failed: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Invitation sent successfully.',
            'debug_link' => config('app.debug') ? $frontendUrl : null // Only for local dev visibility
        ]);
    }

    /**
     * Accept the invitation and register the user.
     * Validates the cryptographic signature of the URL.
     */
    public function acceptInvite(Request $request)
    {
        // 1. Validate the cryptographic signature of the URL
        if (!$request->hasValidSignature()) {
            abort(401, 'Invalid or expired invitation link.');
        }

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'password' => ['required', Password::defaults()],
        ]);

        DB::beginTransaction();

        try {
            $business_id = $request->query('business_id');
            $email = $request->query('email');
            $role = $request->query('role');

            if (User::where('email', $email)->exists()) {
                abort(422, 'User is already registered.');
            }

            // 2. Create the user under the inviter's business_id
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'username' => $email, // Default to email
                'email' => $email,
                'password' => Hash::make($request->password),
                'user_type' => 'user',
                'business_id' => $business_id,
                'allow_login' => true,
            ]);

            // 3. Assign the target role securely
            $user->assignRole($role);

            DB::commit();

            return response()->json([
                'message' => 'Invitation accepted successfully. You can now login.',
                'user' => $user
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to process invitation.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
