<?php

namespace App\Jobs\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\Data\Attendance\GuildAttendanceData;
use App\Http\Integrations\WarcraftLogs\Requests\GetGuildAttendanceRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsConnector;
use App\Jobs\Concerns\ResolvesCharacterNames;
use App\Jobs\WarcraftLogs\Concerns\ReleasesOnRateLimit;
use App\Models\GameVersion;
use App\Models\Report;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;

#[DeleteWhenMissingModels]
#[MaxExceptions(3)]
#[FailOnTimeout]
class FetchAttendanceData implements ShouldQueue
{
    use Batchable, Queueable, ReleasesOnRateLimit, ResolvesCharacterNames;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600; // 10 minutes

    /**
     * The guild's reports with a Warcraft Logs code, keyed by code.
     *
     * @var Collection<string, Report>
     */
    private Collection $reports;

    public function __construct(public Guild $guild) {}

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, SkipIfBatchCancelled|WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            new SkipIfBatchCancelled,
            (new WithoutOverlapping((string) $this->guild->id))
                ->dontRelease()
                ->expireAfter($this->timeout),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(WarcraftLogsConnector $warcraftLogs): void
    {
        $this->reports = $this->loadReports();

        try {
            $attendanceRecords = $this->fetchAttendanceRecords($warcraftLogs);
        } catch (RateLimitReachedException $exception) {
            $this->releaseUntilPointsReset($exception, "attendance for Warcraft Logs guild {$this->guild->id}");

            return;
        }

        $recordsByGameVersion = $this->groupByGameVersion($attendanceRecords);
        $gameVersions = $this->loadGameVersions($recordsByGameVersion->keys());

        foreach ($recordsByGameVersion as $gameVersionId => $guildAttendances) {
            $this->syncGameVersionAttendance($gameVersions->get($gameVersionId), $guildAttendances);
        }

        Log::info('Completed fetching and syncing attendance data.');
    }

    /**
     * Load the guild's reports with a Warcraft Logs code, keyed by code.
     *
     * @return Collection<string, Report>
     */
    private function loadReports(): Collection
    {
        return Report::whereBelongsTo($this->guild, 'warcraftLogsGuild')
            ->whereNotNull('code')
            ->get(['id', 'code', 'game_version_id'])
            ->keyBy('code');
    }

    /**
     * Fetch the guild's attendance records, keeping only those for syncable reports.
     *
     * @return Collection<int, GuildAttendanceData>
     *
     * @throws RateLimitReachedException
     */
    private function fetchAttendanceRecords(WarcraftLogsConnector $warcraftLogs): Collection
    {
        return $warcraftLogs->paginate(new GetGuildAttendanceRequest($this->guild->id, $this->guild->namespace))
            ->collect()
            ->filter(fn (GuildAttendanceData $guildAttendance): bool => $this->hasSyncableReport($guildAttendance))
            ->collect();
    }

    /**
     * Group the attendance records by their report's game version.
     *
     * @param  Collection<int, GuildAttendanceData>  $attendanceRecords
     * @return Collection<int, Collection<int, GuildAttendanceData>>
     */
    private function groupByGameVersion(Collection $attendanceRecords): Collection
    {
        return $attendanceRecords->groupBy(
            fn (GuildAttendanceData $guildAttendance) => $this->reports->get($guildAttendance->code)->game_version_id,
        );
    }

    /**
     * Load the game versions with their attendance-counting characters, keyed by ID.
     *
     * @param  Collection<int, int>  $gameVersionIds
     * @return Collection<int, GameVersion>
     */
    private function loadGameVersions(Collection $gameVersionIds): Collection
    {
        return GameVersion::with(['characters' => fn (HasMany $query) => $query->whereRelation('rank', 'count_attendance', true)])
            ->findMany($gameVersionIds)
            ->keyBy('id');
    }

    /**
     * Resolve the players' characters within one game version and sync each report's attendance.
     *
     * @param  Collection<int, GuildAttendanceData>  $guildAttendances
     */
    private function syncGameVersionAttendance(GameVersion $gameVersion, Collection $guildAttendances): void
    {
        $characterIds = $this->resolveCharacterIds(
            $gameVersion,
            $guildAttendances->flatMap(fn (GuildAttendanceData $guildAttendance) => array_column($guildAttendance->players, 'name')),
            $gameVersion->characters,
            'warcraftlogs.attendance',
        );

        foreach ($guildAttendances as $guildAttendance) {
            $this->syncReportAttendance($guildAttendance, $this->reports->get($guildAttendance->code)->id, $characterIds);
        }
    }

    /**
     * Determine whether the attendance record belongs to a known report with a game version.
     */
    private function hasSyncableReport(GuildAttendanceData $guildAttendance): bool
    {
        $report = $this->reports->get($guildAttendance->code);

        if ($report === null) {
            return false;
        }

        if ($report->game_version_id === null) {
            Log::warning("Report code {$guildAttendance->code} has no game version. Skipping attendance sync for this report.");

            return false;
        }

        return true;
    }

    /**
     * Upsert one report's resolved players and touch the report.
     *
     * @param  Collection<string, int>  $characterIds  Character IDs keyed by player name.
     */
    private function syncReportAttendance(GuildAttendanceData $guildAttendance, string $reportId, Collection $characterIds): void
    {
        $syncData = collect($guildAttendance->players)
            ->filter(fn ($player) => $characterIds->has($player->name))
            ->map(fn ($player) => [
                'character_id' => $characterIds->get($player->name),
                'raid_report_id' => $reportId,
                'presence' => $player->presence,
            ])
            ->values()
            ->all();

        if (empty($syncData)) {
            Log::warning("No valid players found for report code {$guildAttendance->code}. Skipping attendance sync for this report.");

            return;
        }

        DB::table('pivot_characters_raid_reports')->upsert(
            $syncData,
            ['character_id', 'raid_report_id'],
            ['presence']
        );
        Report::find($reportId)?->touch();

        $syncedCount = count($syncData);

        Log::info("Synced attendance data for report code {$guildAttendance->code} with {$syncedCount} records.");
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['warcraftlogs', 'attendance', "warcraft-logs-guild:{$this->guild->id}"];
    }
}
