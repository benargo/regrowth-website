<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Raids\Report;
use Inertia\Inertia;

class RaidingController extends Controller
{
    /**
     * Display the main raiding dashboard, showing upcoming events and recent reports.
     */
    public function index()
    {
        return Inertia::render('Raiding/Index', [
            'upcomingEvents' => Inertia::defer(function () {
                return Event::where('start_time', '>=', now())
                    ->where('start_time', '<=', now()->addWeek()->endOfDay())
                    ->orderBy('start_time')
                    ->get()
                    ->toResourceCollection();
            }),
            'reports' => Inertia::defer(function () {
                return Report::withCount('linkedReports')
                    ->orderBy('start_time', 'desc')
                    ->take(10)
                    ->get()
                    ->toResourceCollection();
            }),
        ]);
    }
}
