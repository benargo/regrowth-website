<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects requests authenticated with a desktop app bearer token, allowing only the site's cookie token.
 */
class EnsureTransientToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token !== null && ! $token->transient()) {
            abort(403);
        }

        return $next($request);
    }
}
