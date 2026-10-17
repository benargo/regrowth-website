<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\BuildAttendanceDashboardStats;
use App\Actions\Attendance\FindReportDateRange;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Data\Attendance\AttendanceDashboardStatsData;
use App\Http\Middleware\RemembersCurrentGameVersion;
use App\Http\Resources\GameVersionResource;
use App\Models\GameVersion;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

#[Middleware('auth')]
#[Middleware(RemembersCurrentGameVersion::class)]
#[Authorize('view-attendance')]
class AttendanceDashboardController extends Controller
{
    /**
     * Display the attendance dashboard of the current game version, with blank
     * statistics when no game version has counted attendance.
     */
    public function __invoke(): Response
    {
        $gameVersions = GameVersion::withCountedAttendance()->get();
        $gameVersion = ResolveCurrentGameVersion::run($gameVersions);

        if ($gameVersion === null) {
            return Inertia::render('Raiding/Attendance/Dashboard', [
                'gameVersion' => null,
                'gameVersions' => GameVersionResource::collection($gameVersions),
                'latestReportDate' => null,
                'stats' => Inertia::defer(fn (): AttendanceDashboardStatsData => AttendanceDashboardStatsData::blank()),
            ]);
        }

        return Inertia::render('Raiding/Attendance/Dashboard', [
            'gameVersion' => new GameVersionResource($gameVersion),
            'gameVersions' => GameVersionResource::collection($gameVersions),
            'latestReportDate' => FindReportDateRange::run($gameVersion)->latest?->toDateString(),
            'stats' => Inertia::defer(fn (): AttendanceDashboardStatsData => BuildAttendanceDashboardStats::run($gameVersion)),
        ]);
    }
}
