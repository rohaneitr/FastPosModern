<?php

namespace App\Domain\IAM\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domain\IAM\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::where('business_id', $request->user()->business_id)
            ->with('roles')
            ->get()
            ->map(function ($u) {
                $u->role = $u->roles->first()->name ?? 'Cashier';
                $u->phone = $u->settings['phone'] ?? '';
                $u->address = $u->settings['address'] ?? '';
                $u->is_active = $u->allow_login;
                return $u;
            });
        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'role' => 'required|string',
        ]);

        $generatedPassword = str()->password(12, true, true, false, false); // 12 chars, letters, numbers, symbols

        $user = User::create([
            'business_id' => $request->user()->business_id,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'username' => explode('@', $validated['email'])[0] . rand(100, 999),
            'email' => $validated['email'],
            'password' => Hash::make($generatedPassword),
            'user_type' => 'tenant',
            'allow_login' => true,
            'settings' => [
                'phone' => $validated['phone'] ?? '',
                'address' => $validated['address'] ?? '',
            ]
        ]);
        
        $user->assignRole($validated['role']);

        return response()->json([
            'message' => 'User created successfully. Please save the generated password!', 
            'user' => $user,
            'generated_password' => $generatedPassword
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $user = User::where('business_id', $request->user()->business_id)->findOrFail($id);
        
        $validated = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:users,email,'.$id,
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'role' => 'required|string',
        ]);

        $settings = $user->settings ?? [];
        $settings['phone'] = $validated['phone'] ?? '';
        $settings['address'] = $validated['address'] ?? '';

        $user->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'settings' => $settings
        ]);

        $user->syncRoles([$validated['role']]);

        return response()->json(['message' => 'User updated successfully']);
    }

    public function destroy(Request $request, $id)
    {
        $user = User::where('business_id', $request->user()->business_id)->findOrFail($id);
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot delete yourself'], 400);
        }
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }
}
