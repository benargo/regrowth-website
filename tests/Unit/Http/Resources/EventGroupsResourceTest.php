<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\EventGroupsResource;
use App\Http\Resources\GuildRankResource;
use App\Models\Character;
use App\Models\Event;
use App\Models\GuildRank;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventGroupsResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_empty_array_when_no_characters_attached(): void
    {
        $event = Event::factory()->create();

        $array = (new EventGroupsResource($event))->toArray(new Request);

        $this->assertSame([], $array);
    }

    #[Test]
    public function it_returns_correct_group_number(): void
    {
        $event = Event::factory()->create();
        $raid = Raid::factory()->create(['max_players' => 10]);
        $event->raids()->attach($raid);

        $character = Character::factory()->withRank()->create();
        $event->characters()->attach($character->id, [
            'group_number' => 1,
            'slot_number' => 1,
            'is_confirmed' => true,
            'is_leader' => false,
            'is_loot_councillor' => false,
            'is_loot_master' => false,
        ]);

        $array = (new EventGroupsResource($event))->toArray(new Request);

        $this->assertCount(1, $array);
        $this->assertSame(1, $array[0]['group_number']);
    }

    #[Test]
    public function it_returns_characters_sorted_by_slot_number_within_a_group(): void
    {
        $event = Event::factory()->create();
        $raid = Raid::factory()->create(['max_players' => 10]);
        $event->raids()->attach($raid);

        $charA = Character::factory()->withRank()->create(['name' => 'Alpha']);
        $charB = Character::factory()->withRank()->create(['name' => 'Beta']);

        $event->characters()->attach($charA->id, [
            'group_number' => 1,
            'slot_number' => 3,
            'is_confirmed' => true,
            'is_leader' => false,
            'is_loot_councillor' => false,
            'is_loot_master' => false,
        ]);
        $event->characters()->attach($charB->id, [
            'group_number' => 1,
            'slot_number' => 1,
            'is_confirmed' => true,
            'is_leader' => false,
            'is_loot_councillor' => false,
            'is_loot_master' => false,
        ]);

        $array = (new EventGroupsResource($event))->toArray(new Request);

        $characters = $array[0]['characters'];
        $this->assertSame(1, $characters[0]['slot_number']);
        $this->assertSame(3, $characters[1]['slot_number']);
    }

    #[Test]
    public function it_returns_is_team_false_when_max_slot_is_five_or_fewer(): void
    {
        $event = Event::factory()->create();
        $raid = Raid::factory()->create(['max_players' => 10]);
        $event->raids()->attach($raid);

        $character = Character::factory()->withRank()->create();
        $event->characters()->attach($character->id, [
            'group_number' => 1,
            'slot_number' => 5,
            'is_confirmed' => true,
            'is_leader' => false,
            'is_loot_councillor' => false,
            'is_loot_master' => false,
        ]);

        $array = (new EventGroupsResource($event))->toArray(new Request);

        $this->assertFalse($array[0]['is_team']);
    }

    #[Test]
    public function it_returns_is_team_true_when_max_slot_exceeds_five(): void
    {
        $event = Event::factory()->create();
        $raid = Raid::factory()->create(['max_players' => 10]);
        $event->raids()->attach($raid);

        $character = Character::factory()->withRank()->create();
        $event->characters()->attach($character->id, [
            'group_number' => 1,
            'slot_number' => 6,
            'is_confirmed' => true,
            'is_leader' => false,
            'is_loot_councillor' => false,
            'is_loot_master' => false,
        ]);

        $array = (new EventGroupsResource($event))->toArray(new Request);

        $this->assertTrue($array[0]['is_team']);
    }

    #[Test]
    public function it_excludes_groups_beyond_max_groups_capacity(): void
    {
        $event = Event::factory()->create();
        $raid = Raid::factory()->create(['max_players' => 10]);
        $event->raids()->attach($raid);

        // 10 max_players / 5 max_slot = 2 groups max
        foreach (range(1, 3) as $groupNumber) {
            $character = Character::factory()->withRank()->create();
            $event->characters()->attach($character->id, [
                'group_number' => $groupNumber,
                'slot_number' => 5,
                'is_confirmed' => true,
                'is_leader' => false,
                'is_loot_councillor' => false,
                'is_loot_master' => false,
            ]);
        }

        $array = (new EventGroupsResource($event))->toArray(new Request);

        $this->assertCount(2, $array);
        $this->assertSame(1, $array[0]['group_number']);
        $this->assertSame(2, $array[1]['group_number']);
    }

    #[Test]
    public function it_returns_correct_character_shape(): void
    {
        $rank = GuildRank::factory()->create();
        $event = Event::factory()->create();
        $raid = Raid::factory()->create(['max_players' => 10]);
        $event->raids()->attach($raid);

        $character = Character::factory()->for($rank, 'rank')->create(['name' => 'Thrall']);
        $event->characters()->attach($character->id, [
            'group_number' => 1,
            'slot_number' => 2,
            'is_confirmed' => true,
            'is_leader' => true,
            'is_loot_councillor' => false,
            'is_loot_master' => true,
        ]);

        $array = (new EventGroupsResource($event))->toArray(new Request);
        $char = $array[0]['characters'][0];

        $this->assertSame($character->id, $char['id']);
        $this->assertSame('Thrall', $char['name']);
        $this->assertArrayHasKey('playable_class', $char);
        $this->assertArrayHasKey('playable_race', $char);
        $this->assertInstanceOf(GuildRankResource::class, $char['rank']);
        $this->assertSame(2, $char['slot_number']);
        $this->assertTrue($char['is_confirmed']);
        $this->assertTrue($char['is_leader']);
        $this->assertFalse($char['is_loot_councillor']);
        $this->assertTrue($char['is_loot_master']);
    }

    #[Test]
    public function it_does_not_include_slot_number_or_role_keys_in_group_shape(): void
    {
        $event = Event::factory()->create();
        $raid = Raid::factory()->create(['max_players' => 10]);
        $event->raids()->attach($raid);

        $character = Character::factory()->withRank()->create();
        $event->characters()->attach($character->id, [
            'group_number' => 1,
            'slot_number' => 1,
            'is_confirmed' => true,
            'is_leader' => false,
            'is_loot_councillor' => false,
            'is_loot_master' => false,
        ]);

        $array = (new EventGroupsResource($event))->toArray(new Request);
        $group = $array[0];

        $this->assertArrayHasKey('group_number', $group);
        $this->assertArrayHasKey('characters', $group);
        $this->assertArrayHasKey('is_team', $group);
        $this->assertArrayNotHasKey('slot_number', $group);
    }
}
