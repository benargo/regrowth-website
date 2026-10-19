<?php

namespace App\Http\Controllers;

use App\Actions\Events\ListUpcomingRaidingEvents;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Actions\Reports\ListRecentReports;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RaidingController extends Controller
{
    /**
     * Redirect to the next event.
     */
    public function comps(): RedirectResponse
    {
        $nextEvent = Event::live()->where('end_time', '>=', now())->orderBy('start_time')->first();

        if ($nextEvent) {
            return redirect(route('raiding.plans.show', $nextEvent), 303);
        }

        return redirect(route('raiding.index'), 303);
    }

    /**
     * Display the raiding hub: the current game version's events in the coming week
     * and its five most recent reports.
     */
    public function index(Request $request): Response
    {
        $gameVersion = ResolveCurrentGameVersion::run();

        return Inertia::render('Raiding/Index', [
            'upcomingEvents' => Inertia::defer(fn (): array => ListUpcomingRaidingEvents::run($gameVersion, $request)),
            'reports' => Inertia::defer(fn (): array => ListRecentReports::run($gameVersion, $request)),
        ]);
    }
}
