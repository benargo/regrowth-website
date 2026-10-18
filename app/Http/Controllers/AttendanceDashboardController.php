<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\BuildAttendanceDashboardStats;
use App\Actions\Attendance\FindReportDateRange;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Data\Attendance\AttendanceDashboardStatsData;
use App\Http\Resources\GameVersionResource;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

#[Middleware('auth')]
#[Authorize('view-attendance')]
class AttendanceDashboardController extends Controller
{
    /**
     * Display the attendance dashboard of the current game version, or an empty
     * state when that version has no counted attendance.
     */
    public function __invoke(): Response
    {
        $gameVersion = ResolveCurrentGameVersion::run();

        if ($gameVersion === null || ! $gameVersion->hasCountedAttendance()) {
            return Inertia::render('Raiding/Attendance/Dashboard', [
                'gameVersion' => $gameVersion === null ? null : new GameVersionResource($gameVersion),
                'hasAttendance' => false,
            ]);
        }

        return Inertia::render('Raiding/Attendance/Dashboard', [
            'gameVersion' => new GameVersionResource($gameVersion),
            'hasAttendance' => true,
            'latestReportDate' => FindReportDateRange::run($gameVersion)->latest?->toDateString(),
            'stats' => Inertia::defer(fn (): AttendanceDashboardStatsData => BuildAttendanceDashboardStats::run($gameVersion)),
        ]);
    }
}
