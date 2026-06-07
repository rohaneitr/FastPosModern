<?php

namespace App\Domain\IAM\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function getProfile(Request $request)
    {
        $user = $request->user()->load(['roles', 'permissions', 'business']);
        return response()->json($user);
    }

    public function updateProfile(Request $request)
    {
        // ... (preserving original for compatibility if needed, but we will add the new POST /update method)
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
            'preferred_currency' => 'nullable|string|size:3',
        ]);
        
        $user->update($validated);
        
        return response()->json(['message' => 'Preferences updated successfully']);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'theme_preference' => 'nullable|string|in:light,dark,system',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
        ]);

        $updateData = [
            'first_name' => $request->first_name,
            'theme_preference' => $request->theme_preference ?? 'system',
        ];

        if ($request->has('last_name')) {
            $updateData['last_name'] = $request->last_name;
        }

        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $path = $request->file('avatar')->store('uploads/avatars', 'public');
            $updateData['avatar'] = $path;
        }

        $user->update($updateData);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh()
        ]);
    }
}
