<?php

namespace App\Http\Controllers\Raid;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /**
     * Display the main attendance page, which may include filters and options for viewing attendance data. This method should return an Inertia response that renders the appropriate view for the attendance page.
     */
    public function index(): Response
    {
        return Inertia::render('Raids/Attendance/Index');
    }
}