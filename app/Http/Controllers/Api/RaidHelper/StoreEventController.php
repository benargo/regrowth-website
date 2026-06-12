<?php

namespace App\Http\Controllers\Api\RaidHelper;

use App\Http\Controllers\Controller;
use App\Http\Integrations\RaidHelper\Data\Events\EventData;
use App\Http\Middleware\VerifyRaidHelperWebhook;
use App\Jobs\RaidHelper\SyncEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware(VerifyRaidHelperWebhook::class)]
class StoreEventController extends Controller
{
    /**
     * Handle a Raid Helper event-create webhook by dispatching a SyncEvent job.
     */
    public function __invoke(Request $request): Response
    {
        $request->validate([
            'id' => ['required', 'string'],
            'channelId' => ['required', 'string'],
            'leaderId' => ['required', 'string'],
            'leaderName' => ['required', 'string'],
            'title' => ['required', 'string'],
            'description' => ['present', 'nullable'],
            'startTime' => ['required', 'integer'],
            'endTime' => ['required', 'integer'],
            'lastUpdated' => ['required', 'integer'],
            'color' => ['required', 'string'],
        ]);

        $payload = array_merge($request->all(), [
            'description' => $request->input('description') ?? '',
        ]);

        SyncEvent::dispatch(EventData::from($payload));

        return response()->noContent(202);
    }
}
