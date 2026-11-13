<?php

namespace App\Http\Middleware;

use App\Data\DesktopClient\DesktopClientHeadersData;
use App\Models\DesktopClientToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a desktop app bearer token, valid desktop client headers, and a token bound to the device in
 * `X-Desktop-Client-Id`. Answers `401` with the same body for any device that is not the token's binding.
 */
class EnsureDesktopClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token === null || $token->transient()) {
            abort(403);
        }

        $headers = DesktopClientHeadersData::tryFromRequest($request);

        if ($headers === null) {
            return response()->json([
                'message' => 'Missing or invalid X-Desktop-Client-Id or X-Desktop-Client-Name.',
            ], 400);
        }

        $isBoundToDevice = DesktopClientToken::whereKey($token->oauth_access_token_id)
            ->where('desktop_client_id', $headers->id)
            ->exists();

        if (! $isBoundToDevice) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
