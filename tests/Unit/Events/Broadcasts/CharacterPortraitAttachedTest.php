<?php

namespace Tests\Unit\Events\Broadcasts;

use App\Events\Broadcasts\CharacterPortraitAttached;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
#[Group('broadcasting')]
class CharacterPortraitAttachedTest extends TestCase
{
    #[Group('contract')]
    #[Test]
    public function it_broadcasts_on_a_public_channel_named_for_the_character(): void
    {
        $event = new CharacterPortraitAttached(characterId: 42);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame('character.42', $channels[0]->name);
    }

    #[Group('contract')]
    #[Test]
    public function it_uses_a_plain_public_channel_not_a_private_channel(): void
    {
        $event = new CharacterPortraitAttached(characterId: 1);

        $channels = $event->broadcastOn();

        $this->assertNotInstanceOf(PrivateChannel::class, $channels[0]);
    }

    #[Group('contract')]
    #[Test]
    public function it_broadcasts_as_the_correct_event_name(): void
    {
        $event = new CharacterPortraitAttached(characterId: 1);

        $this->assertSame('CharacterPortraitAttached', $event->broadcastAs());
    }

    #[Group('contract')]
    #[Test]
    public function it_broadcasts_the_character_id(): void
    {
        $event = new CharacterPortraitAttached(characterId: 99);

        $this->assertSame(['id' => 99], $event->broadcastWith());
    }

    #[Group('contract')]
    #[Test]
    public function its_payload_stays_well_under_the_reverb_message_limit(): void
    {
        $event = new CharacterPortraitAttached(characterId: 53156234);

        $bytes = strlen((string) json_encode($event->broadcastWith()));

        $this->assertLessThan(10_000, $bytes);
    }
}
