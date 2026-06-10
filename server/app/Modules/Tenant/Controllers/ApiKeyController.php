<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function index(Request $request)
    {
        $tokens = $request->user()->tokens()->get()->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'created_at' => $token->created_at,
                'last_used_at' => $token->last_used_at,
            ];
        });

        return response()->json($tokens);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Creating a token with full wildcard permissions for external API usage
        $token = $request->user()->createToken($request->name, ['*']);

        return response()->json([
            'message' => 'Token created successfully',
            'token' => $token->plainTextToken
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->tokens()->where('id', $id)->delete();

        return response()->json(['message' => 'Token revoked successfully']);
    }
}
