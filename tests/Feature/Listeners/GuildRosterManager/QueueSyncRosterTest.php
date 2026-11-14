<?php

namespace Tests\Feature\Listeners\GuildRosterManager;

use App\Data\GuildRosterManager\SyncChangesData;
use App\Events\DesktopClient\SyncCommitting;
use App\Jobs\GuildRosterManager\SyncRoster;
use App\Listeners\GuildRosterManager\QueueSyncRoster;
use App\Models\GuildRosterManager\Upload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class QueueSyncRosterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_queues_sync_roster_with_the_changes(): void
    {
        Bus::fake();
        $sync = Upload::factory()->committed(1)->create();
        $changes = new SyncChangesData(
            members: ['Player-5826-0A1B2C3D'],
            leavers: [],
            alt_groups: [],
            departed_members: [],
            previous_alt_groups: [],
            ban_ids: [],
        );

        (new QueueSyncRoster)->handle(new SyncCommitting($sync, $changes));

        Bus::assertDispatchedTimes(SyncRoster::class, 1);
        Bus::assertDispatched(SyncRoster::class, fn (SyncRoster $job): bool => $job->uploadId === $sync->id && $job->changes === $changes);
    }

    #[Test]
    #[Group('contract')]
    public function it_listens_for_sync_committing(): void
    {
        Event::fake();

        Event::assertListening(SyncCommitting::class, QueueSyncRoster::class);
    }
}
