<?php

namespace App\Http\Controllers\Api\RaidHelper;

use App\Http\Controllers\Controller;
use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionData;
use App\Http\Middleware\VerifyRaidHelperWebhook;
use App\Jobs\RaidHelper\SyncComposition;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware(VerifyRaidHelperWebhook::class)]
class UpdateCompositionController extends Controller
{
    /**
     * Handle a Raid Helper comp-update webhook by dispatching a SyncComposition job.
     */
    public function __invoke(Request $request): Response
    {
        $request->validate(['eventId' => ['required', 'string']]);

        $data = CompositionData::from($request->except('eventId'));

        SyncComposition::dispatch($request->input('eventId'), $data);

        return response()->noContent(202);
    }
}
