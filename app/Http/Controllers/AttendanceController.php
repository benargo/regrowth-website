<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlannedAbsenceResource;
use App\Services\Attendance\Dashboard;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(private readonly Dashboard $dashboard) {}

    public function index(): Response
    {
        return Inertia::render('Raids/Attendance/Index', [
            'latestReportDate' => $this->dashboard->latestReportDate(),
            'stats' => Inertia::defer(fn () => [
                ...$this->dashboard->stats(),
                'upcomingAbsences' => PlannedAbsenceResource::collection($this->dashboard->upcomingAbsences())
                    ->toResponse(request())
                    ->getData(true)['data'],
            ]),
        ]);
    }
}
