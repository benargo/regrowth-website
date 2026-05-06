<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\EventBenchedCharactersResource;
use App\Http\Resources\GuildRankResource;
use App\Models\Character;
use App\Models\Event;
use App\Models\GuildRank;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventBenchedCharactersResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_empty_array_when_no_characters_are_attached(): void
    {
        $event = Event::factory()->create();

        $array = (new EventBenchedCharactersResource($event))->toArray(new Request);

        $this->assertSame([], $array);
    }

    #[Test]
    public function it_returns_empty_array_when_is_team_true(): void
    {
        $event = Event::factory()->create();
        $raid = Raid::factory()->create(['max_players' => 10]);
        $event->raids()->attach($raid);

        // slot 6 triggers is_team = true
        $character = Character::factory()->withRank()->create();
        $event->characters()->attach($character->id, [
            'group_number' => 3,
            'slot_number' => 6,
            'is_confirmed' => true,
            'is_leader' => false,
            'is_loot_councillor' => false,
            'is_loot_master' => false,
        ]);

        $array = (new EventBenchedCharactersResource($event))->toArray(new Request);

        $this->assertSame([], $array);
    }

    #[Test]
    public function it_returns_characters_whose_group_number_exceeds_max_groups(): void
    {
        $event = Event::factory()->create();
        $raid = Raid::factory()->create(['max_players' => 10]);
        $event->raids()->attach($raid);

        // 10 max_players / 5 max_slot = 2 max groups; group 3 is benched
        $active = Character::factory()->withRank()->create(['name' => 'Active']);
        $benched = Character::factory()->withRank()->create(['name' => 'Benched']);

        $event->characters()->attach($active->id, [
            'group_number' => 1,
            'slot_number' => 5,
            'is_confirmed' => true,
            'is_leader' => false,
            'is_loot_councillor' => false,
            'is_loot_master' => false,
        ]);
        $event->characters()->attach($benched->id, [
            'group_number' => 3,
            'slot_number' => 5,
            'is_confirmed' => false,
            'is_leader' => false,
            'is_loot_councillor' => false,
            'is_loot_master' => false,
        ]);

        $array = (new EventBenchedCharactersResource($event))->toArray(new Request);

        $this->assertCount(1, $array);
        $this->assertSame($benched->id, $array[0]['id']);
        $this->assertSame('Benched', $array[0]['name']);
    }

    #[Test]
    public function it_returns_correct_benched_character_shape(): void
    {
        $rank = GuildRank::factory()->create();
        $event = Event::factory()->create();
        $raid = Raid::factory()->create(['max_players' => 10]);
        $event->raids()->attach($raid);

        $character = Character::factory()->for($rank, 'rank')->create(['name' => 'Sylvanas']);
        $event->characters()->attach($character->id, [
            'group_number' => 3,
            'slot_number' => 5,
            'is_confirmed' => false,
            'is_leader' => false,
            'is_loot_councillor' => false,
            'is_loot_master' => false,
        ]);

        $array = (new EventBenchedCharactersResource($event))->toArray(new Request);
        $char = $array[0];

        $this->assertSame($character->id, $char['id']);
        $this->assertSame('Sylvanas', $char['name']);
        $this->assertArrayHasKey('playable_class', $char);
        $this->assertArrayHasKey('playable_race', $char);
        $this->assertInstanceOf(GuildRankResource::class, $char['rank']);
        $this->assertFalse($char['is_confirmed']);
    }

    #[Test]
    public function it_does_not_include_slot_number_or_role_fields_for_benched_characters(): void
    {
        $event = Event::factory()->create();
        $raid = Raid::factory()->create(['max_players' => 10]);
        $event->raids()->attach($raid);

        $character = Character::factory()->withRank()->create();
        $event->characters()->attach($character->id, [
            'group_number' => 3,
            'slot_number' => 5,
            'is_confirmed' => true,
            'is_leader' => true,
            'is_loot_councillor' => true,
            'is_loot_master' => true,
        ]);

        $array = (new EventBenchedCharactersResource($event))->toArray(new Request);
        $char = $array[0];

        $this->assertArrayNotHasKey('slot_number', $char);
        $this->assertArrayNotHasKey('is_leader', $char);
        $this->assertArrayNotHasKey('is_loot_councillor', $char);
        $this->assertArrayNotHasKey('is_loot_master', $char);
    }
}
