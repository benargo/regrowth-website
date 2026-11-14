<?php

namespace Tests\Feature\Events\DesktopClient;

use App\Enums\GuildRosterManager\UploadOutcome;
use App\Events\DesktopClient\SyncCommitted;
use App\Models\GuildRosterManager\Upload;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
#[Group('broadcasting')]
class SyncCommittedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[Group('contract')]
    public function it_broadcasts_on_the_desktop_clients_private_channel(): void
    {
        $this->freezeSecond();
        $capturedAt = now()->subMinutes(3);
        $sync = Upload::factory()->staging()->create(['captured_at' => $capturedAt]);
        $sync->markOutcome(UploadOutcome::NoChanges, ['revision' => 4]);

        $event = new SyncCommitted($sync);

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame("private-desktop-clients.{$sync->desktop_client_id}", $channels[0]->name);
        $this->assertSame('SyncCommitted', $event->broadcastAs());
        $this->assertSame([
            'syncId' => $sync->id,
            'revision' => 4,
            'outcome' => 'no_changes',
            'startedAt' => $capturedAt->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ], $event->broadcastWith());
    }
}
