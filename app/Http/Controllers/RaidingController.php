<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventSummaryResource;
use App\Models\Event;
use App\Models\Raids\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class RaidingController extends Controller
{
    /**
     * Redirect to the next event.
     */
    public function comps(): RedirectResponse
    {
        $nextEvent = Event::where('start_time', '>=', now())->orderBy('start_time')->first();

        if ($nextEvent) {
            return redirect(route('raiding.plans.show', $nextEvent), 303);
        }

        return redirect(route('raiding.index'), 303);
    }

    /**
     * Display the main raiding dashboard, showing upcoming events and recent reports.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Raiding/Index', [
            'upcomingEvents' => Inertia::defer(function () use ($request) {
                return Cache::tags('raiding', 'events')->remember('events:upcoming', now()->addMinutes(10), function () use ($request) {
                    return EventSummaryResource::collection(
                        Event::where('start_time', '>=', now())
                            ->where('start_time', '<=', now()->addWeek()->endOfDay())
                            ->orderBy('start_time')
                            ->get()
                    )->resolve($request);
                });
            }),
            'reports' => Inertia::defer(function () use ($request) {
                return Cache::tags('raiding', 'reports')->remember('reports:recent:10', now()->addMinutes(10), function () use ($request) {
                    return Report::withCount('linkedReports')
                        ->orderBy('start_time', 'desc')
                        ->take(10)
                        ->get()
                        ->toResourceCollection()
                        ->resolve($request);
                });
            }),
        ]);
    }
}
