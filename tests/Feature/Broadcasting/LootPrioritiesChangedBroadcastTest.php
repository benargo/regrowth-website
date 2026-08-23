<?php

namespace Tests\Feature\Broadcasting;

use App\Events\Broadcasts\LootPrioritiesChanged;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
#[Group('broadcasting')]
class LootPrioritiesChangedBroadcastTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_broadcasts_on_the_private_loot_priorities_channel(): void
    {
        $channels = (new LootPrioritiesChanged)->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('private-loot-priorities', $channels[0]->name);
    }

    #[Test]
    #[Group('contract')]
    public function it_broadcasts_as_loot_priorities_changed(): void
    {
        $this->assertEquals('LootPrioritiesChanged', (new LootPrioritiesChanged)->broadcastAs());
    }

    #[Test]
    #[Group('contract')]
    public function it_carries_no_payload(): void
    {
        $this->assertSame([], (new LootPrioritiesChanged)->broadcastWith());
    }

    #[Test]
    #[Group('contract')]
    public function it_broadcasts_immediately(): void
    {
        $this->assertInstanceOf(ShouldBroadcastNow::class, new LootPrioritiesChanged);
    }
}
