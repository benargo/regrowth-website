<?php

namespace App\Http\Controllers;

use App\Models\DiscordNotification;
use App\Notifications\DailyQuestsMessage;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('view-officer-dashboard')]
#[Authorize('audit-daily-quests')]
class DailyQuestsAuditController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $paginator = DiscordNotification::where('type', DailyQuestsMessage::class)
            ->latest()
            ->paginate(20)
            ->appends($request->query());

        return Inertia::render('Dashboard/DailyQuests/Audit', [
            'entries' => $paginator,
        ]);
    }
}
