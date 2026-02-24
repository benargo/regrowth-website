<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\SyncDiscordRoles;
use App\Jobs\SyncDiscordUsers;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncDiscordRolesAndUsersTest extends TestCase
{
    #[Test]
    public function it_dispatches_exactly_one_batch(): void
    {
        Bus::fake();

        $this->artisan('app:sync-discord')
            ->assertSuccessful();

        Bus::assertBatchCount(1);
    }

    #[Test]
    public function it_dispatches_a_batch_containing_both_jobs(): void
    {
        Bus::fake();

        $this->artisan('app:sync-discord')
            ->assertSuccessful();

        Bus::assertBatched(function (PendingBatch $batch) {
            return $batch->jobs->contains(fn ($job) => $job instanceof SyncDiscordRoles)
                && $batch->jobs->contains(fn ($job) => $job instanceof SyncDiscordUsers);
        });
    }

    #[Test]
    public function it_dispatches_exactly_two_jobs_in_the_batch(): void
    {
        Bus::fake();

        $this->artisan('app:sync-discord')
            ->assertSuccessful();

        Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() === 2);
    }
}
