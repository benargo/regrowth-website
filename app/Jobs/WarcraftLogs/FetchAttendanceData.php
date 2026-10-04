<?php

namespace App\Jobs\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\Data\Attendance\GuildAttendanceData;
use App\Http\Integrations\WarcraftLogs\Requests\GetGuildAttendanceRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsConnector;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\Report;
use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;

#[MaxExceptions(3)]
#[FailOnTimeout]
class FetchAttendanceData implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600; // 10 minutes

    public function __construct(public GameVersion $gameVersion) {}

    /**
     * Get the middleware the job should pass through.
     *
     * The lock is keyed on the game version: with no key it would be per job class,
     * and dontRelease() would silently drop a second version's job while the first runs.
     * It expires with the job's timeout, so a crashed worker cannot hold it forever.
     *
     * @return array<int, SkipIfBatchCancelled|WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            new SkipIfBatchCancelled,
            (new WithoutOverlapping((string) $this->gameVersion->id))
                ->dontRelease()
                ->expireAfter($this->timeout),
        ];
    }

    /**
     * Keep retrying rate-limit releases until the points window has passed.
     * #[MaxExceptions(3)] and #[FailOnTimeout] still fail the job after three
     * real exceptions or one timeout.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(2);
    }

    /**
     * Execute the job.
     */
    public function handle(WarcraftLogsConnector $warcraftLogs): void
    {
        if ($this->gameVersion->warcraftlogs_guild === null) {
            Log::warning("Skipping attendance for game version {$this->gameVersion->id}: no Warcraft Logs guild.");

            return;
        }

        if ($this->gameVersion->warcraftlogs_namespace === null) {
            Log::warning("Skipping attendance for game version {$this->gameVersion->id}: no Warcraft Logs namespace.");

            return;
        }

        $reports = Report::whereNotNull('code')->get()->keyBy('code');

        $paginator = $warcraftLogs->paginate(new GetGuildAttendanceRequest(
            $this->gameVersion->warcraftlogs_guild,
            $this->gameVersion->warcraftlogs_namespace,
        ));

        $attendanceRecords = $paginator->collect()->whereIn('code', $reports->keys());

        $characters = Character::with('rank')
            ->whereHas('rank', fn (Builder $q) => $q->where('count_attendance', true))
            ->get()
            ->keyBy('name');

        try {
            $attendanceRecords->each(function (GuildAttendanceData $guildAttendance) use ($reports, $characters) {
                $report = $reports->get($guildAttendance->code);

                if ($report === null) {
                    Log::info("Skipping report code {$guildAttendance->code} as it does not exist in the database.");

                    return;
                }

                // Filter the players to only those that are in our character list and should be counted for attendance.
                $filteredAttendanceRecord = $guildAttendance->filterPlayers($characters->keys()->toArray());

                if (empty($filteredAttendanceRecord->players)) {
                    Log::warning("No valid players found for report code {$guildAttendance->code}. Skipping attendance sync for this report.");

                    return;
                }

                $syncData = [];

                foreach ($filteredAttendanceRecord->players as $player) {
                    $character = $characters->get($player->name);

                    if ($character === null) {
                        Log::warning("Character {$player->name} not found in database. Skipping attendance record for this player in report code {$guildAttendance->code}.");

                        continue;
                    }

                    $syncData[] = [
                        'character_id' => $character->id,
                        'raid_report_id' => $report->id,
                        'presence' => $player->presence,
                    ];
                }

                if (! empty($syncData)) {
                    DB::table('pivot_characters_raid_reports')->upsert(
                        $syncData,
                        ['character_id', 'raid_report_id'],
                        ['presence']
                    );
                    $report->touch();

                    $syncedCount = count($syncData);

                    Log::info("Synced attendance data for report code {$guildAttendance->code} with {$syncedCount} records.");
                }
            });
        } catch (RateLimitReachedException $exception) {
            $this->releaseUntilPointsReset($exception);

            return;
        }

        Log::info('Completed fetching and syncing attendance data.');
    }

    private function releaseUntilPointsReset(RateLimitReachedException $exception): void
    {
        $limit = $exception->getLimit();
        $seconds = $limit->getRemainingSeconds();

        Log::warning("Warcraft Logs rate limit reached while fetching attendance for game version {$this->gameVersion->id}; releasing for {$seconds} seconds.", [
            'limit' => $limit->getName(),
        ]);

        $this->release($seconds);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['warcraftlogs', 'attendance', "game-version:{$this->gameVersion->id}"];
    }
}
