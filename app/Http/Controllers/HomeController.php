<?php

namespace App\Http\Controllers;

use App\Actions\Events\BuildEventsForViewer;
use App\Actions\Race\BuildRaceStandings;
use App\Models\Event;
use App\Models\GameVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    private const UPCOMING_EVENT_LIMIT = 5;

    public function __invoke(Request $request): Response
    {
        return Inertia::render('Home', [
            'race' => ($forever = GameVersion::forever()) ? BuildRaceStandings::run($forever, withRacers: false) : null,
            'officers' => config('guild.officers'),
            'canViewPlans' => $request->user() !== null,
            'upcomingEvents' => Inertia::defer(function () use ($request) {
                return Cache::tags('raiding', 'events')->remember(
                    $request->user() ? 'events:upcoming:home:members' : 'events:upcoming:home:visitors',
                    now()->addMinutes(10),
                    fn () => BuildEventsForViewer::run(
                        Event::live()
                            ->where('end_time', '>=', now())
                            ->with('gameVersion.media')
                            ->orderBy('start_time')
                            ->take(self::UPCOMING_EVENT_LIMIT),
                        $request,
                    )->resolve($request)
                );
            }),
        ]);
    }
}
