<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function createToken(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $token = Str::random(40);

        $apiToken = ApiToken::create([
            'user_id' => $request->user->id,
            'name' => $request->name,
            'token' => hash('sha256', $token),
            'abilities' => $request->abilities,
            'expires_at' => $request->expires_at,
        ]);

        return response()->json([
            'token' => $token,
            'name' => $apiToken->name,
            'abilities' => $apiToken->abilities,
            'expires_at' => $apiToken->expires_at,
        ]);
    }

    public function listTokens(Request $request)
    {
        $tokens = ApiToken::where('user_id', $request->user->id)
            ->latest()
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'last_used_at' => $token->last_used_at,
                    'expires_at' => $token->expires_at,
                    'created_at' => $token->created_at,
                ];
            });

        return response()->json(['tokens' => $tokens]);
    }

    public function revokeToken(Request $request, ApiToken $token)
    {
        if ($token->user_id !== $request->user->id) {
            abort(403);
        }

        $token->delete();

        return response()->json(['message' => 'Token revoked successfully']);
    }

    public function revokeAllTokens(Request $request)
    {
        ApiToken::where('user_id', $request->user->id)->delete();

        return response()->json(['message' => 'All tokens revoked successfully']);
    }
}
