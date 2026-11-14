<?php

namespace App\Http\Controllers\Api\GuildRosterManager;

use App\Actions\GuildRosterManager\StageSyncRows;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureDesktopClient;
use App\Http\Middleware\EnsureSyncIsOpen;
use App\Http\Middleware\InflateGzipRequest;
use App\Http\Middleware\TouchDesktopClient;
use App\Http\Requests\GuildRosterManager\StoreAltGroupBatchRequest;
use App\Http\Requests\GuildRosterManager\StoreBanBatchRequest;
use App\Http\Requests\GuildRosterManager\StoreLeaverBatchRequest;
use App\Http\Requests\GuildRosterManager\StoreMemberBatchRequest;
use App\Http\Requests\GuildRosterManager\StoreSyncBatchRequest;
use App\Models\DesktopClient;
use App\Models\GuildRosterManager\Upload;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Laravel\Passport\Http\Middleware\CheckToken;

#[Middleware(InflateGzipRequest::class.':required')]
#[Middleware(CheckToken::class.':grm:upload')]
#[Middleware(EnsureDesktopClient::class)]
#[Middleware(TouchDesktopClient::class)]
#[Middleware(EnsureSyncIsOpen::class)]
#[Middleware('throttle:grm-sync-requests')]
#[Authorize('uploadGuildRoster', DesktopClient::class)]
class SyncBatchController extends Controller
{
    /**
     * Stage a batch of member rows for the sync.
     */
    public function members(StoreMemberBatchRequest $request, Upload $sync): JsonResponse
    {
        return $this->stage($request, $sync);
    }

    /**
     * Stage a batch of leaver rows for the sync.
     */
    public function leavers(StoreLeaverBatchRequest $request, Upload $sync): JsonResponse
    {
        return $this->stage($request, $sync);
    }

    /**
     * Stage a batch of alt group rows for the sync.
     */
    public function altGroups(StoreAltGroupBatchRequest $request, Upload $sync): JsonResponse
    {
        return $this->stage($request, $sync);
    }

    /**
     * Stage a batch of ban rows for the sync.
     */
    public function bans(StoreBanBatchRequest $request, Upload $sync): JsonResponse
    {
        return $this->stage($request, $sync);
    }

    private function stage(StoreSyncBatchRequest $request, Upload $sync): JsonResponse
    {
        return response()->json([
            'staged' => StageSyncRows::run($sync, $request->kind(), $request->rows()),
        ]);
    }
}
