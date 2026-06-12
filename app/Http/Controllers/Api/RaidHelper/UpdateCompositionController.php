<?php

namespace App\Http\Controllers\Api\RaidHelper;

use App\Http\Controllers\Controller;
use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionData;
use App\Http\Middleware\VerifyRaidHelperWebhook;
use App\Http\Requests\RaidHelper\UpdateCompositionRequest;
use App\Jobs\RaidHelper\SyncComposition;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware(VerifyRaidHelperWebhook::class)]
class UpdateCompositionController extends Controller
{
    /**
     * Handle a Raid Helper comp-update webhook by dispatching a SyncComposition job.
     */
    public function __invoke(UpdateCompositionRequest $request): Response
    {
        $data = CompositionData::from($request->all());

        SyncComposition::dispatch($data->id, $data);

        return response()->noContent(202);
    }
}
