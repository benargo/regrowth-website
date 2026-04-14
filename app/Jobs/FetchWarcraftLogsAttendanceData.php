<?php

namespace App\Jobs;

use App\Models\Character;
use App\Models\Raids\Report;
use App\Services\WarcraftLogs\Attendance;
use App\Services\WarcraftLogs\Data\GuildAttendance;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchWarcraftLogsAttendanceData implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600; // 10 minutes

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [new SkipIfBatchCancelled];
    }

    /**
     * Execute the job.
     */
    public function handle(Attendance $attendanceService): void
    {
        $reports = Report::all()->keyBy('code');

        $attendanceRecords = $attendanceService->lazy()->whereIn('code', $reports->keys());

        $characters = Character::with('rank')
            ->whereHas('rank', fn (Builder $q) => $q->where('count_attendance', true))
            ->get()
            ->keyBy('name');

        $attendanceRecords->each(function (GuildAttendance $guildAttendance) use ($reports, $characters) {
            $report = $reports->get($guildAttendance->code);

            if ($report === null) {
                Log::info('Skipping report code '.$guildAttendance->code.' as it does not exist in the database.');

                return;
            }

            // Filter the players to only those that are in our character list and should be counted for attendance.
            $filteredAttendanceRecord = $guildAttendance->filterPlayers($characters->keys()->toArray());

            if (empty($filteredAttendanceRecord->players)) {
                Log::warning('No valid players found for report code '.$guildAttendance->code.'. Skipping attendance sync for this report.');

                return;
            }

            $syncData = [];

            foreach ($filteredAttendanceRecord->players as $player) {
                $character = $characters->get($player->name);

                if ($character === null) {
                    Log::warning('Character '.$player->name.' not found in database. Skipping attendance record for this player in report code '.$guildAttendance->code.'.');

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

                Log::info('Synced attendance data for report code '.$guildAttendance->code.' with '.count($syncData).' records.');
            }
        });

        Log::info('Completed fetching and syncing attendance data.');
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['warcraftlogs', 'attendance'];
    }
}
