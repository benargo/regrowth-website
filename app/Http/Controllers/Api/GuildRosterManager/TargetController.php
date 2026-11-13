<?php

namespace App\Http\Controllers\Api\GuildRosterManager;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureDesktopClient;
use App\Http\Middleware\TouchDesktopClient;
use App\Http\Resources\GuildRosterManager\TargetResource;
use App\Models\DesktopClient;
use App\Models\GameVersion;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Laravel\Passport\Http\Middleware\CheckToken;

#[Middleware(CheckToken::class.':grm:targets')]
#[Middleware(EnsureDesktopClient::class)]
#[Middleware(TouchDesktopClient::class)]
#[Authorize('uploadGuildRoster', DesktopClient::class)]
class TargetController extends Controller
{
    /**
     * List the current guild rosters that have a WoW client folder to sync.
     */
    public function __invoke(): AnonymousResourceCollection
    {
        return TargetResource::collection(
            GameVersion::currentRosters()->filter(fn (GameVersion $gameVersion): bool => $gameVersion->wow_client_folder !== null)->values(),
        );
    }
}
