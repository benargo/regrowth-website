<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\BuildAttendanceDashboardStats;
use App\Actions\Attendance\FindReportDateRange;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Data\Attendance\AttendanceDashboardStatsData;
use App\Http\Resources\GameVersionResource;
use App\Models\GameVersion;
use App\Models\User;
use Illuminate\Http\Request;
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
     * state when that version has no counted attendance. The empty state flags
     * reports whose guild tags do not count attendance, with a link to the
     * version's Warcraft Logs guild for users who can update its tags.
     */
    public function __invoke(Request $request): Response
    {
        $gameVersion = ResolveCurrentGameVersion::run();

        if ($gameVersion === null || ! $gameVersion->hasCountedAttendance()) {
            $hasUncountedReports = $gameVersion?->reports()->exists() ?? false;

            return Inertia::render('Raiding/Attendance/Dashboard', [
                'gameVersion' => $gameVersion === null ? null : new GameVersionResource($gameVersion),
                'hasAttendance' => false,
                'hasUncountedReports' => $hasUncountedReports,
                'guildTagsUrl' => $hasUncountedReports ? $this->guildTagsUrl($request->user(), $gameVersion) : null,
            ]);
        }

        return Inertia::render('Raiding/Attendance/Dashboard', [
            'gameVersion' => new GameVersionResource($gameVersion),
            'hasAttendance' => true,
            'latestReportDate' => FindReportDateRange::run($gameVersion)->latest?->toDateString(),
            'stats' => Inertia::defer(fn (): AttendanceDashboardStatsData => BuildAttendanceDashboardStats::run($gameVersion)),
        ]);
    }

    /**
     * The URL of the game version's Warcraft Logs guild, or null when the user
     * cannot view that guild or update its tags.
     */
    private function guildTagsUrl(User $user, GameVersion $gameVersion): ?string
    {
        $guild = $gameVersion->warcraftLogsGuild;
        $guildTag = $guild?->guildTags()->first();

        if ($guildTag === null || $user->cannot('view', $guild) || $user->cannot('update', $guildTag)) {
            return null;
        }

        return route('management.warcraftlogs.guilds.show', $guild);
    }
}
