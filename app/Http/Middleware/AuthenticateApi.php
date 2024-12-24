<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;

class AuthenticateApi
{
    public function handle(Request $request, Closure $next, $ability = null)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'message' => 'API token not provided',
            ], 401);
        }

        $apiToken = ApiToken::where('token', hash('sha256', $token))->first();

        if (!$apiToken || $apiToken->isExpired()) {
            return response()->json([
                'message' => 'Invalid or expired API token',
            ], 401);
        }

        if ($ability && !$apiToken->hasAbility($ability)) {
            return response()->json([
                'message' => 'Token does not have the required ability',
            ], 403);
        }

        $apiToken->update([
            'last_used_at' => now(),
        ]);

        $request->user = $apiToken->user;
        $request->apiToken = $apiToken;

        return $next($request);
    }
}
