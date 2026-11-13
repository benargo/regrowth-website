<?php

namespace App\Http\Middleware;

use App\Actions\DesktopClient\RecordDesktopClient;
use App\Data\DesktopClient\DesktopClientHeadersData;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records the desktop client named by the request headers and puts it on the request as `desktopClient`.
 */
class TouchDesktopClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $headers = DesktopClientHeadersData::tryFromRequest($request);

        $request->attributes->set(
            'desktopClient',
            RecordDesktopClient::run(
                $user,
                $headers->id,
                $headers->name,
                $user->currentAccessToken()->oauth_access_token_id,
            ),
        );

        return $next($request);
    }
}
