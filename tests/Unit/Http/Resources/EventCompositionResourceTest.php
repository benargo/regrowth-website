<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\EventCompositionResource;
use App\Models\Character;
use App\Models\Event;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class EventCompositionResourceTest extends TestCase
{
    use RefreshDatabase;

    private function makeResource(Event $event): array
    {
        $event->load('raids.bosses.media', 'assignments.group', 'characters.rank');

        return (new EventCompositionResource($event))->toArray(new Request);
    }

    // ============ Structure ============

    #[Test]
    public function it_returns_only_groups_and_bench_keys(): void
    {
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertArrayHasKey('groups', $array);
        $this->assertArrayHasKey('bench', $array);
        $this->assertCount(2, $array);
    }

    #[Test]
    public function it_does_not_return_top_level_event_fields(): void
    {
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertArrayNotHasKey('id', $array);
        $this->assertArrayNotHasKey('title', $array);
        $this->assertArrayNotHasKey('start_time', $array);
        $this->assertArrayNotHasKey('end_time', $array);
        $this->assertArrayNotHasKey('background', $array);
        $this->assertArrayNotHasKey('raids', $array);
        $this->assertArrayNotHasKey('assignments', $array);
        $this->assertArrayNotHasKey('channel', $array);
    }

    // ============ Groups ============

    #[Test]
    public function it_returns_empty_groups_when_no_characters(): void
    {
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertSame([], $array['groups']);
    }

    #[Test]
    public function it_returns_groups_with_correct_character_shape(): void
    {
        $raid = Raid::factory()->create(['max_players' => 10]);
        $event = Event::factory()->hasAttached($raid, [], 'raids')->create();
        $character = Character::factory()->withRank()->create();
        $event->characters()->attach($character->id, [
            'slot_number' => 1,
            'group_number' => 1,
            'is_confirmed' => true,
            'is_leader' => false,
            'is_loot_councillor' => false,
            'is_loot_master' => false,
        ]);

        $array = $this->makeResource($event);

        $this->assertCount(1, $array['groups']);
        $group = $array['groups'][0];
        $this->assertSame(1, $group['group_number']);
        $this->assertArrayHasKey('is_team', $group);

        $char = $group['characters'][0];
        $this->assertSame($character->id, $char['id']);
        $this->assertSame($character->name, $char['name']);
        $this->assertArrayHasKey('playable_class', $char);
        $this->assertArrayHasKey('rank', $char);
        $this->assertArrayHasKey('name', $char['rank']);
        $this->assertArrayHasKey('position', $char['rank']);
        $this->assertSame(1, $char['slot_number']);
        $this->assertTrue($char['is_confirmed']);
        $this->assertArrayHasKey('is_leader', $char);
        $this->assertArrayHasKey('is_loot_councillor', $char);
        $this->assertArrayHasKey('is_loot_master', $char);
    }

    #[Test]
    public function it_excludes_benched_characters_from_groups(): void
    {
        $event = Event::factory()->create();
        $character = Character::factory()->withRank()->create();
        $event->characters()->attach($character->id, [
            'slot_number' => null,
            'group_number' => null,
            'is_confirmed' => false,
            'is_benched' => true,
        ]);

        $array = $this->makeResource($event);

        $this->assertSame([], $array['groups']);
    }

    // ============ Bench ============

    #[Test]
    public function it_returns_empty_bench_when_no_characters(): void
    {
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertSame([], $array['bench']);
    }

    #[Test]
    public function it_returns_benched_characters_in_bench(): void
    {
        $benchedChar = Character::factory()->withRank()->create(['name' => 'Thrall']);

        $event = Event::factory()->create();
        $event->characters()->attach($benchedChar->id, [
            'slot_number' => null,
            'group_number' => null,
            'is_confirmed' => false,
            'is_benched' => true,
        ]);

        $array = $this->makeResource($event);

        $this->assertCount(1, $array['bench']);
        $this->assertSame($benchedChar->name, $array['bench'][0]['name']);
    }

    #[Test]
    public function it_returns_bench_characters_with_expected_shape(): void
    {
        $benchedChar = Character::factory()->withRank()->create();

        $event = Event::factory()->create();
        $event->characters()->attach($benchedChar->id, [
            'slot_number' => null,
            'group_number' => null,
            'is_confirmed' => false,
            'is_benched' => true,
        ]);

        $array = $this->makeResource($event);

        $bench = $array['bench'][0];
        $this->assertArrayHasKey('id', $bench);
        $this->assertArrayHasKey('name', $bench);
        $this->assertArrayHasKey('playable_class', $bench);
        $this->assertArrayHasKey('rank', $bench);
        $this->assertArrayHasKey('name', $bench['rank']);
        $this->assertArrayHasKey('position', $bench['rank']);
        $this->assertArrayNotHasKey('slot_number', $bench);
        $this->assertArrayNotHasKey('is_confirmed', $bench);
    }

    #[Test]
    public function it_does_not_include_active_roster_characters_in_bench(): void
    {
        $inComp = Character::factory()->withRank()->create(['name' => 'Jaina']);
        $benchedChar = Character::factory()->withRank()->create(['name' => 'Thrall']);

        $event = Event::factory()->create();
        $event->characters()->attach($inComp->id, [
            'slot_number' => 1,
            'group_number' => 1,
            'is_confirmed' => true,
            'is_benched' => false,
        ]);
        $event->characters()->attach($benchedChar->id, [
            'slot_number' => null,
            'group_number' => null,
            'is_confirmed' => false,
            'is_benched' => true,
        ]);

        $array = $this->makeResource($event);

        $this->assertCount(1, $array['bench']);
        $this->assertSame('Thrall', $array['bench'][0]['name']);
    }
}
