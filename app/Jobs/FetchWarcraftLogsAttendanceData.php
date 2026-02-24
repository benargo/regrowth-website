<?php

namespace App\Jobs;

use App\Exceptions\EmptyCollectionException;
use App\Models\Character;
use App\Models\WarcraftLogs\Report;
use App\Services\WarcraftLogs\Attendance;
use App\Services\WarcraftLogs\Data\GuildAttendance;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FetchWarcraftLogsAttendanceData implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [new SkipIfBatchCancelled];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Collection $guildTags,
        protected ?Carbon $since = null,
        protected ?Carbon $before = null,
    ) {}

    /**
     * Execute the job.
     *
     * @throws \Exception if no guild tags or ranks are configured for attendance tracking.
     */
    public function handle(Attendance $attendanceService): void
    {
        Log::info('Starting to fetch and sync attendance data for '.($this->since ? 'reports since '.$this->since->toIso8601String() : 'all reports').'.');

        // Validate that we have guild tags to process
        if ($this->guildTags->isEmpty()) {
            throw new EmptyCollectionException('No guild tags configured for attendance tracking.');
        }

        $tagIds = $this->guildTags->pluck('id')->toArray();

        $attendance = $attendanceService->tags($tagIds)->lazy();

        if ($this->since !== null) {
            $attendance = $attendance->filter(
                fn (GuildAttendance $record) => $record->startTime->gte($this->since)
            );
        }

        if ($this->before !== null) {
            $attendance = $attendance->filter(
                fn (GuildAttendance $record) => $record->startTime->lte($this->before)
            );
        }

        $characters = Character::with('rank')
            ->whereHas('rank', fn (Builder $q) => $q->where('count_attendance', true))
            ->get()
            ->keyBy('name');

        foreach ($attendance as $guildAttendance) {
            $report = Report::find($guildAttendance->code);

            if ($report === null) {
                continue;
            }

            $syncData = [];

            foreach ($guildAttendance->players as $player) {
                $character = $characters->get($player->name);

                if ($character === null) {
                    continue;
                }

                $syncData[$character->id] = ['presence' => $player->presence];
            }

            $report->characters()->syncWithoutDetaching($syncData);
        }

        Log::info('Completed fetching and syncing attendance data for '.($this->since ? 'reports since '.$this->since->toIso8601String() : 'all reports').'. Processed '.$attendance->count().' attendance records.');
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
