<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\BuildAttendanceMatrix;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Data\Attendance\AttendanceScatterPointData;
use App\Http\Middleware\RemembersCurrentGameVersion;
use App\Http\Resources\GameVersionResource;
use App\Models\GameVersion;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

#[Middleware('auth')]
#[Middleware(RemembersCurrentGameVersion::class)]
#[Authorize('view-attendance')]
class AttendanceGraphsController extends Controller
{
    /**
     * Display the attendance graphs of the current game version.
     */
    public function __invoke(): Response
    {
        $gameVersions = GameVersion::withCountedAttendance()->get();
        $gameVersion = ResolveCurrentGameVersion::run($gameVersions);

        return Inertia::render('Raiding/Attendance/Graphs', [
            'gameVersion' => $gameVersion === null ? null : new GameVersionResource($gameVersion),
            'gameVersions' => GameVersionResource::collection($gameVersions),
            'scatterPoints' => Inertia::defer(fn () => $this->getScatterPoints($gameVersion)),
        ]);
    }

    /**
     * Get the scatter points for the attendance graph of the given game version.
     *
     * @return Collection<int, AttendanceScatterPointData>
     */
    private function getScatterPoints(?GameVersion $gameVersion): Collection
    {
        if ($gameVersion === null) {
            return collect();
        }

        $matrix = BuildAttendanceMatrix::run($gameVersion);

        return AttendanceScatterPointData::collect($matrix->rows);
    }
}
