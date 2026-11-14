<?php

namespace Tests\Unit\Events\DesktopClient;

use App\Data\GuildRosterManager\SyncChangesData;
use App\Events\DesktopClient\SyncCommitting;
use App\Models\GuildRosterManager\Upload;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class SyncCommittingTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_dispatches_after_the_database_transaction_commits(): void
    {
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $this->makeEvent());
    }

    #[Test]
    #[Group('contract')]
    public function it_does_not_broadcast(): void
    {
        $this->assertNotInstanceOf(ShouldBroadcast::class, $this->makeEvent());
    }

    #[Test]
    public function it_carries_the_sync_and_its_changes(): void
    {
        $sync = new Upload;
        $changes = $this->makeChanges();

        $event = new SyncCommitting($sync, $changes);

        $this->assertSame($sync, $event->sync);
        $this->assertSame($changes, $event->changes);
    }

    // ==================== helpers ====================

    private function makeEvent(): SyncCommitting
    {
        return new SyncCommitting(new Upload, $this->makeChanges());
    }

    private function makeChanges(): SyncChangesData
    {
        return new SyncChangesData(
            members: ['Player-1-0A'],
            leavers: [],
            alt_groups: [],
            departed_members: [],
            previous_alt_groups: [],
            ban_ids: [],
        );
    }
}
