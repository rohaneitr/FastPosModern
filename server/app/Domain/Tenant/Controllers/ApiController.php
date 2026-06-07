<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    /**
     * Get list of API tokens for the tenant
     */
    public function getTokens(Request $request)
    {
        $user = $request->user();
        $tokens = $user->tokens()->get(['id', 'name', 'last_used_at', 'created_at']);
        return response()->json($tokens);
    }

    /**
     * Create a new API token
     */
    public function createToken(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $user = $request->user();

        // Ensure token has business context
        $token = $user->createToken($request->name, ['business_id:' . $user->business_id]);

        return response()->json([
            'message' => 'Token generated successfully',
            'token' => $token->plainTextToken
        ]);
    }

    /**
     * Revoke an API token
     */
    public function revokeToken(Request $request, $id)
    {
        $user = $request->user();
        $user->tokens()->where('id', $id)->delete();
        return response()->json(['message' => 'Token revoked']);
    }
}
