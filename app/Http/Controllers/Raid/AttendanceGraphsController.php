<?php

namespace App\Http\Controllers\Raid;

use App\Http\Controllers\Controller;
use App\Services\Attendance\Graphs;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceGraphsController extends Controller
{
    public function __construct(private readonly Graphs $graphs) {}

    public function index(): Response
    {
        return Inertia::render('Raids/Attendance/Graphs', [
            'scatterPoints' => Inertia::defer(
                fn () => $this->graphs->scatterPoints()->toResponse(request())->getData(true)['data'],
            ),
        ]);
    }
}
