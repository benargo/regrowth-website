<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Raids\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class RaidingController extends Controller
{
    /**
     * Display the main raiding dashboard, showing upcoming events and recent reports.
     */
    public function index(Request $request)
    {
        return Inertia::render('Raiding/Index', [
            'upcomingEvents' => Inertia::defer(function () use ($request) {
                return Cache::tags('raiding', 'events')->remember('events:upcoming', now()->addMinutes(10), function () use ($request) {
                    return Event::where('start_time', '>=', now())
                        ->where('start_time', '<=', now()->addWeek()->endOfDay())
                        ->orderBy('start_time')
                        ->get()
                        ->toResourceCollection()
                        ->resolve($request);
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
