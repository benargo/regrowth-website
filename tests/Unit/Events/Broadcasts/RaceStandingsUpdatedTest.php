<?php

namespace Tests\Unit\Events\Broadcasts;

use App\Events\Broadcasts\RaceStandingsUpdated;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
#[Group('broadcasting')]
class RaceStandingsUpdatedTest extends TestCase
{
    #[Group('contract')]
    #[Test]
    public function it_broadcasts_on_the_public_race_to_60_channel(): void
    {
        $event = new RaceStandingsUpdated(Carbon::now(), true);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertNotInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('race-to-60', $channels[0]->name);
    }

    #[Group('contract')]
    #[Test]
    public function it_broadcasts_as_the_correct_event_name(): void
    {
        $event = new RaceStandingsUpdated(Carbon::now(), false);

        $this->assertSame('RaceStandingsUpdated', $event->broadcastAs());
    }

    #[Group('contract')]
    #[Test]
    public function it_broadcasts_the_check_time_and_change_flag(): void
    {
        $event = new RaceStandingsUpdated(Carbon::parse('2026-11-20 19:42:00', 'UTC'), true);

        $this->assertSame([
            'checkedAt' => '2026-11-20T19:42:00+00:00',
            'hasChanges' => true,
        ], $event->broadcastWith());
    }

    #[Group('contract')]
    #[Test]
    public function it_broadcasts_an_unchanged_flag(): void
    {
        $event = new RaceStandingsUpdated(Carbon::parse('2026-11-20 19:43:00', 'UTC'), false);

        $this->assertSame([
            'checkedAt' => '2026-11-20T19:43:00+00:00',
            'hasChanges' => false,
        ], $event->broadcastWith());
    }
}
