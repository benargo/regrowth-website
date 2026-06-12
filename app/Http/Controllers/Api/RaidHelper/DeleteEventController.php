<?php

namespace App\Http\Controllers\Api\RaidHelper;

use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifyRaidHelperWebhook;
use App\Jobs\RaidHelper\DeleteEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware(VerifyRaidHelperWebhook::class)]
class DeleteEventController extends Controller
{
    /**
     * Handle a Raid Helper event-delete webhook by dispatching a DeleteEvent job.
     */
    public function __invoke(Request $request): Response
    {
        $request->validate(['id' => ['required', 'string']]);

        DeleteEvent::dispatch($request->input('id'));

        return response()->noContent(202);
    }
}
