<?php

namespace App\Jobs\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\Data\Attendance\GuildAttendanceData;
use App\Http\Integrations\WarcraftLogs\Requests\GetGuildAttendanceRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsConnector;
use App\Jobs\WarcraftLogs\Concerns\ReleasesOnRateLimit;
use App\Models\Character;
use App\Models\Report;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;

#[DeleteWhenMissingModels]
#[MaxExceptions(3)]
#[FailOnTimeout]
class FetchAttendanceData implements ShouldQueue
{
    use Batchable, Queueable, ReleasesOnRateLimit;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600; // 10 minutes

    public function __construct(public Guild $guild) {}

    /**
     * Get the middleware the job should pass through.
     *
     * The lock is keyed on the guild: with no key it would be per job class,
     * and dontRelease() would silently drop a second guild's job while the first runs.
     * It expires with the job's timeout, so a crashed worker cannot hold it forever.
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
        $reportIds = Report::whereBelongsTo($this->guild, 'warcraftLogsGuild')
            ->whereNotNull('code')
            ->pluck('id', 'code');

        $paginator = $warcraftLogs->paginate(new GetGuildAttendanceRequest(
            $this->guild->id,
            $this->guild->namespace,
        ));

        $attendanceRecords = $paginator->collect()->filter(fn (GuildAttendanceData $guildAttendance) => $reportIds->has($guildAttendance->code));

        $characters = Character::whereHas('rank', fn (Builder $q) => $q->where('count_attendance', true))
            ->get(['id', 'name'])
            ->keyBy('name');

        try {
            $attendanceRecords->each(function (GuildAttendanceData $guildAttendance) use ($reportIds, $characters) {
                $reportId = $reportIds->get($guildAttendance->code);

                // Only players in our character list that should be counted for attendance are synced.
                $syncData = collect($guildAttendance->players)
                    ->filter(fn ($player) => $characters->has($player->name))
                    ->map(fn ($player) => [
                        'character_id' => $characters->get($player->name)->id,
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
                Report::whereKey($reportId)->get()->each->touch();

                $syncedCount = count($syncData);

                Log::info("Synced attendance data for report code {$guildAttendance->code} with {$syncedCount} records.");
            });
        } catch (RateLimitReachedException $exception) {
            $this->releaseUntilPointsReset($exception, "attendance for Warcraft Logs guild {$this->guild->id}");

            return;
        }

        Log::info('Completed fetching and syncing attendance data.');
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
