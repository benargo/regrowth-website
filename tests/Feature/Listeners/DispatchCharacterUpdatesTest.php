<?php

namespace Tests\Feature\Listeners;

use App\Events\GuildRosterFetched;
use App\Jobs\UpdateCharacterFromRoster;
use App\Listeners\DispatchCharacterUpdates;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DispatchCharacterUpdatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_dispatches_batch_with_job_for_each_member_when_not_throttled(): void
    {
        Bus::fake();

        $roster = [
            'members' => [
                ['character' => ['id' => 1, 'name' => 'CharOne', 'level' => 60], 'rank' => 0],
                ['character' => ['id' => 2, 'name' => 'CharTwo', 'level' => 70], 'rank' => 3],
            ],
        ];

        $listener = new DispatchCharacterUpdates;
        $listener->handle(new GuildRosterFetched($roster));

        Bus::assertBatched(function (PendingBatch $batch) {
            return $batch->jobs->count() === 2;
        });
    }

    public function test_does_not_dispatch_jobs_when_within_six_hour_throttle(): void
    {
        Bus::fake();

        Cache::put('guild.roster.updates.last_dispatched', true, now()->addHours(6));

        $roster = [
            'members' => [
                ['character' => ['id' => 1, 'name' => 'CharOne', 'level' => 60], 'rank' => 0],
            ],
        ];

        $listener = new DispatchCharacterUpdates;
        $listener->handle(new GuildRosterFetched($roster));

        Bus::assertNothingBatched();
    }

    public function test_dispatches_jobs_after_throttle_expires(): void
    {
        Bus::fake();

        $roster = [
            'members' => [
                ['character' => ['id' => 1, 'name' => 'CharOne', 'level' => 60], 'rank' => 0],
            ],
        ];

        $listener = new DispatchCharacterUpdates;

        // First call — dispatches and sets throttle
        $listener->handle(new GuildRosterFetched($roster));
        Bus::assertBatchCount(1);

        // Simulate throttle expiry
        Cache::forget('guild.roster.updates.last_dispatched');

        // Second call after expiry — dispatches again
        $listener->handle(new GuildRosterFetched($roster));
        Bus::assertBatchCount(2);
    }

    public function test_second_call_within_throttle_window_is_skipped(): void
    {
        Bus::fake();

        $roster = [
            'members' => [
                ['character' => ['id' => 1, 'name' => 'CharOne', 'level' => 60], 'rank' => 0],
            ],
        ];

        $listener = new DispatchCharacterUpdates;

        $listener->handle(new GuildRosterFetched($roster));
        $listener->handle(new GuildRosterFetched($roster));

        Bus::assertBatchCount(1);
    }

    public function test_dispatches_no_jobs_when_members_array_is_empty(): void
    {
        Bus::fake();

        $listener = new DispatchCharacterUpdates;
        $listener->handle(new GuildRosterFetched(['members' => []]));

        Bus::assertNothingBatched();
    }

    public function test_dispatches_no_jobs_when_members_key_is_missing(): void
    {
        Bus::fake();

        $listener = new DispatchCharacterUpdates;
        $listener->handle(new GuildRosterFetched(['guild' => ['name' => 'Regrowth']]));

        Bus::assertNothingBatched();
    }

    public function test_each_dispatched_job_receives_correct_member_data(): void
    {
        Bus::fake();

        $memberData = ['character' => ['id' => 42, 'name' => 'HeroChar', 'level' => 70], 'rank' => 1];
        $roster = ['members' => [$memberData]];

        $listener = new DispatchCharacterUpdates;
        $listener->handle(new GuildRosterFetched($roster));

        Bus::assertBatched(function (PendingBatch $batch) use ($memberData) {
            /** @var UpdateCharacterFromRoster $job */
            $job = $batch->jobs->first();

            return $job instanceof UpdateCharacterFromRoster
                && $job->characterData === $memberData;
        });
    }
}
