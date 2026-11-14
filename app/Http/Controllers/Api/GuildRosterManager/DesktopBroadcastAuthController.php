<?php

namespace App\Http\Controllers\Api\GuildRosterManager;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureDesktopClient;
use App\Http\Middleware\TouchDesktopClient;
use App\Models\DesktopClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Passport\Http\Middleware\CheckToken;

#[Middleware(CheckToken::class.':grm:upload')]
#[Middleware(EnsureDesktopClient::class)]
#[Middleware(TouchDesktopClient::class)]
#[Middleware('throttle:grm-sync-requests')]
#[Authorize('uploadGuildRoster', DesktopClient::class)]
class DesktopBroadcastAuthController extends Controller
{
    /**
     * Authorise the desktop client's device token for its own private `desktop-clients.{id}` channel, answering `403` for any other channel.
     */
    public function __invoke(Request $request): mixed
    {
        abort_unless(str_starts_with((string) $request->input('channel_name'), 'private-desktop-clients.'), 403);

        return Broadcast::auth($request);
    }
}
