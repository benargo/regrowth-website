<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\BuildAttendanceMatrix;
use App\Data\Attendance\AttendanceMatrixData;
use App\Http\Requests\Raid\AttendanceMatrixRequest;
use App\Http\Resources\GameVersionResource;
use App\Http\Resources\GuildRankResource;
use App\Http\Resources\WarcraftLogs\GuildTagResource;
use App\Http\Resources\ZoneResource;
use App\Models\GameVersion;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Zone;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

#[Middleware('auth')]
#[Authorize('view-attendance')]
class AttendanceMatrixController extends Controller
{
    /**
     * Display the attendance matrix of the current game version.
     */
    public function __invoke(AttendanceMatrixRequest $request): Response
    {
        $gameVersion = $request->gameVersion();

        if ($gameVersion === null || ! $request->hasCountedAttendance()) {
            return Inertia::render('Raiding/Attendance/Matrix', [
                'gameVersion' => $gameVersion === null ? null : new GameVersionResource($gameVersion),
                'hasAttendance' => false,
            ]);
        }

        return Inertia::render('Raiding/Attendance/Matrix', [
            'gameVersion' => new GameVersionResource($gameVersion),
            'hasAttendance' => true,
            'ranks' => $this->ranks($gameVersion),
            'zones' => $this->zones($gameVersion),
            'guildTags' => $this->guildTags($gameVersion),
            'filters' => $request->filters(),
            'earliestDate' => $request->earliestDate(),
            'matrix' => Inertia::defer(fn (): array => $this->matrix($request, $gameVersion)),
        ]);
    }

    /**
     * The game version's guild ranks in rank order.
     */
    private function ranks(GameVersion $gameVersion): AnonymousResourceCollection
    {
        return GuildRankResource::collection($gameVersion->guildRanks()->ordered()->get());
    }

    /**
     * The zones of the game version's reports, ordered by name.
     */
    private function zones(GameVersion $gameVersion): AnonymousResourceCollection
    {
        return ZoneResource::collection(Zone::whereIn(
            'id',
            $gameVersion->reports()->select('zone_id')->whereNotNull('zone_id')->distinct(),
        )->orderBy('name')->get());
    }

    /**
     * The guild tags of the game version's reports, ordered by name.
     */
    private function guildTags(GameVersion $gameVersion): AnonymousResourceCollection
    {
        return GuildTagResource::collection(GuildTag::whereIn(
            'id',
            $gameVersion->reports()->select('guild_tag_id')->whereNotNull('guild_tag_id')->distinct(),
        )->orderBy('name')->get());
    }

    /**
     * The cached raid columns and character rows of the matrix.
     *
     * @return array{columns: array<int, mixed>, rows: array<int, mixed>}
     */
    private function matrix(AttendanceMatrixRequest $request, GameVersion $gameVersion): array
    {
        return Cache::tags(['attendance'])->remember(
            $request->cacheKey('attendance:matrix:'),
            now()->addHours(24),
            fn (): array => $this->buildMatrix($request, $gameVersion)->toArray(),
        );
    }

    /**
     * Build the attendance matrix for the game version using the request's filters.
     */
    private function buildMatrix(AttendanceMatrixRequest $request, GameVersion $gameVersion): AttendanceMatrixData
    {
        return BuildAttendanceMatrix::run(
            $gameVersion,
            rankIds: $request->rankIds(),
            zoneIds: $request->zoneIds(),
            guildTagIds: $request->guildTagIds(),
            since: $request->sinceDate(),
            before: $request->beforeDate(),
            combineLinkedCharacters: $request->combineLinkedCharacters(),
            character: $request->character(),
        );
    }
}
