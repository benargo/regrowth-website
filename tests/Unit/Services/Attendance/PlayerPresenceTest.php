<?php

namespace Tests\Unit\Services\Attendance;

use App\Models\Character;
use App\Models\GuildRank;
use App\Services\Attendance\PlayerPresence;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JsonSerializable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerPresenceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_exposes_the_character_and_presence(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);

        $presence = new PlayerPresence($character, 1);

        $this->assertSame($character, $presence->character);
        $this->assertSame(1, $presence->presence);
    }

    #[Test]
    public function it_implements_arrayable_and_json_serializable(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['rank_id' => $rank->id]);

        $presence = new PlayerPresence($character, 1);

        $this->assertInstanceOf(Arrayable::class, $presence);
        $this->assertInstanceOf(JsonSerializable::class, $presence);
    }

    #[Test]
    public function to_array_includes_character_fields_and_presence(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);

        $array = (new PlayerPresence($character, 2))->toArray();

        $this->assertSame($character->id, $array['id']);
        $this->assertSame('Thrall', $array['name']);
        $this->assertSame($rank->id, $array['rank_id']);
        $this->assertArrayHasKey('playable_class', $array);
        $this->assertSame(2, $array['presence']);
    }

    #[Test]
    public function json_serialize_matches_to_array(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['rank_id' => $rank->id]);

        $presence = new PlayerPresence($character, 0);

        $this->assertSame($presence->toArray(), $presence->jsonSerialize());
    }
}
