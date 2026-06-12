<?php

namespace App\Http\Controllers\Api\RaidHelper;

use App\Http\Controllers\Controller;
use App\Http\Integrations\RaidHelper\Data\Events\EventData;
use App\Http\Middleware\VerifyRaidHelperWebhook;
use App\Http\Requests\RaidHelper\EventWebhookRequest;
use App\Jobs\RaidHelper\SyncEvent;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware(VerifyRaidHelperWebhook::class)]
class SyncEventController extends Controller
{
    public function __invoke(EventWebhookRequest $request): Response
    {
        SyncEvent::dispatch(EventData::from($request->all()));

        return response()->noContent(202);
    }
}
