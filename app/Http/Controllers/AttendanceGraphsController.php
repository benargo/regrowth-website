<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\BuildAttendanceMatrix;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Data\Attendance\AttendanceScatterPointData;
use App\Http\Resources\GameVersionResource;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

#[Middleware('auth')]
#[Authorize('view-attendance')]
class AttendanceGraphsController extends Controller
{
    /**
     * Display the attendance graphs of the current game version, or an empty
     * state when that version has no counted attendance.
     */
    public function __invoke(): Response
    {
        $gameVersion = ResolveCurrentGameVersion::run();

        if ($gameVersion === null || ! $gameVersion->hasCountedAttendance()) {
            return Inertia::render('Raiding/Attendance/Graphs', [
                'gameVersion' => $gameVersion === null ? null : new GameVersionResource($gameVersion),
                'hasAttendance' => false,
            ]);
        }

        return Inertia::render('Raiding/Attendance/Graphs', [
            'gameVersion' => new GameVersionResource($gameVersion),
            'hasAttendance' => true,
            'scatterPoints' => Inertia::defer(fn () => AttendanceScatterPointData::collect(BuildAttendanceMatrix::run($gameVersion)->rows)),
        ]);
    }
}
