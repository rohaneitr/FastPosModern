<?php

namespace App\Domain\IAM\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function getProfile(Request $request)
    {
        $user = $request->user()->load(['roles', 'permissions', 'business']);
        return response()->json($user);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'timezone' => 'nullable|string',
        ]);

        $user->update($validated);

        // Log activity
        DB::table('user_activities')->insert([
            'user_id' => $user->id,
            'action' => 'Profile Updated',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Profile updated successfully', 'user' => $user]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['errors' => ['current_password' => ['The provided password does not match your current password.']]], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Log activity
        DB::table('user_activities')->insert([
            'user_id' => $user->id,
            'action' => 'Password Changed',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Password changed successfully']);
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = $request->user();
        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update(['avatar' => $path]);

        return response()->json(['message' => 'Avatar updated successfully', 'avatar_url' => asset('storage/' . $path)]);
    }

    public function getActivities(Request $request)
    {
        $activities = DB::table('user_activities')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
            
        return response()->json($activities);
    }
    
    public function updatePreferences(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'language' => 'nullable|string|in:en,bn',
            'preferences' => 'nullable|array',
            'two_factor_enabled' => 'nullable|boolean',
        ]);
        
        $user->update($validated);
        
        return response()->json(['message' => 'Preferences updated successfully']);
    }
}
