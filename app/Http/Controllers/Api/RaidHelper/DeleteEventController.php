<?php

namespace App\Http\Controllers\Api\RaidHelper;

use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifyRaidHelperWebhook;
use App\Http\Requests\RaidHelper\EventWebhookRequest;
use App\Jobs\RaidHelper\DeleteEvent;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware(VerifyRaidHelperWebhook::class)]
class DeleteEventController extends Controller
{
    public function __invoke(EventWebhookRequest $request): Response
    {
        DeleteEvent::dispatch($request->input('id'));

        return response()->noContent(202);
    }
}
