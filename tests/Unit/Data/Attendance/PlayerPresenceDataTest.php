<?php

namespace Tests\Unit\Data\Attendance;

use App\Data\Attendance\PlayerPresenceData;
use App\Enums\Presence;
use App\Models\Character;
use App\Models\PlayableClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class PlayerPresenceDataTest extends TestCase
{
    #[Test]
    public function it_exposes_the_character_and_presence(): void
    {
        $character = $this->character();

        $player = new PlayerPresenceData($character, Presence::Benched);

        $this->assertSame($character, $player->character);
        $this->assertSame(Presence::Benched, $player->presence);
    }

    #[Test]
    public function to_array_emits_character_fields_and_presence(): void
    {
        $array = (new PlayerPresenceData($this->character(), Presence::Present))->toArray();

        $this->assertSame(7, $array['id']);
        $this->assertSame('Thrall', $array['name']);
        $this->assertSame(3, $array['rank_id']);
        $this->assertSame(Presence::Present, $array['presence']);
    }

    #[Test]
    public function json_serialize_matches_to_array(): void
    {
        $player = new PlayerPresenceData($this->character(), Presence::Present);

        $this->assertSame($player->toArray(), $player->jsonSerialize());
    }

    #[Test]
    public function to_array_includes_the_playable_class(): void
    {
        $playableClass = PlayableClass::factory()->make(['id' => 1, 'name' => 'Shaman']);
        $character = $this->character()->setRelation('playableClass', $playableClass);

        $this->assertSame($playableClass, (new PlayerPresenceData($character, Presence::Present))->toArray()['playable_class']);
    }

    #[Test]
    public function to_array_includes_a_null_playable_class(): void
    {
        $this->assertNull((new PlayerPresenceData($this->character(), Presence::Present))->toArray()['playable_class']);
    }

    // ==================== helpers ====================

    private function character(): Character
    {
        return Character::factory()->withoutParents()->make(['id' => 7, 'name' => 'Thrall', 'rank_id' => 3, 'playable_class_id' => null]);
    }
}
