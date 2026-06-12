<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class MobileAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $fingerprint = $request->header('X-Device-Fingerprint');
        if (!$fingerprint) {
            return response()->json(['error' => 'FPM Security: X-Device-Fingerprint header is required.'], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // Issue token binding the device fingerprint as the strict token name
        $token = $user->createToken($fingerprint, ['mobile-access'])->plainTextToken;

        return response()->json([
            'message' => 'Mobile authenticated successfully',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'business_id' => $user->business_id
            ]
        ]);
    }
}
