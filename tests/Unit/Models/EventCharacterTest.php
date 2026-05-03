<?php

namespace Tests\Unit\Models;

use App\Models\Character;
use App\Models\Event;
use App\Models\EventCharacter;
use Illuminate\Database\Eloquent\Relations\Pivot;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class EventCharacterTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Event::class;
    }

    private function createPivot(array $overrides = []): EventCharacter
    {
        $event = Event::factory()->create();
        $character = Character::factory()->create();

        $event->characters()->attach($character->id, $overrides);

        return $event->characters()->withPivot([
            'slot_number', 'group_number', 'is_confirmed', 'is_leader', 'is_loot_councillor', 'is_loot_master',
        ])->first()->pivot;
    }

    #[Test]
    public function it_extends_pivot(): void
    {
        $this->assertInstanceOf(Pivot::class, new EventCharacter);
    }

    #[Test]
    public function it_uses_pivot_events_characters_table(): void
    {
        $pivot = new EventCharacter;

        $this->assertSame('pivot_events_characters', $pivot->getTable());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $pivot = new EventCharacter;

        $this->assertFillable($pivot, [
            'event_id',
            'character_id',
            'slot_number',
            'group_number',
            'is_confirmed',
            'is_leader',
            'is_loot_councillor',
            'is_loot_master',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $pivot = new EventCharacter;

        $this->assertCasts($pivot, [
            'slot_number' => 'integer',
            'group_number' => 'integer',
            'is_confirmed' => 'boolean',
            'is_leader' => 'boolean',
            'is_loot_councillor' => 'boolean',
            'is_loot_master' => 'boolean',
        ]);
    }

    #[Test]
    public function boolean_flags_default_to_false(): void
    {
        $pivot = new EventCharacter;

        $this->assertFalse($pivot->is_confirmed);
        $this->assertFalse($pivot->is_leader);
        $this->assertFalse($pivot->is_loot_councillor);
        $this->assertFalse($pivot->is_loot_master);
    }

    #[Test]
    public function slot_number_and_group_number_are_nullable(): void
    {
        $pivot = $this->createPivot();

        $this->assertNull($pivot->slot_number);
        $this->assertNull($pivot->group_number);
    }

    #[Test]
    public function it_stores_slot_and_group_numbers(): void
    {
        $pivot = $this->createPivot(['slot_number' => 2, 'group_number' => 1]);

        $this->assertSame(2, $pivot->slot_number);
        $this->assertSame(1, $pivot->group_number);
    }

    #[Test]
    public function it_can_be_set_as_confirmed(): void
    {
        $pivot = $this->createPivot(['is_confirmed' => true]);

        $this->assertTrue($pivot->is_confirmed);
    }

    #[Test]
    public function it_can_be_set_as_leader(): void
    {
        $pivot = $this->createPivot(['is_leader' => true]);

        $this->assertTrue($pivot->is_leader);
    }

    #[Test]
    public function it_can_be_set_as_loot_councillor(): void
    {
        $pivot = $this->createPivot(['is_loot_councillor' => true]);

        $this->assertTrue($pivot->is_loot_councillor);
    }

    #[Test]
    public function it_can_be_set_as_loot_master(): void
    {
        $pivot = $this->createPivot(['is_loot_master' => true]);

        $this->assertTrue($pivot->is_loot_master);
    }
}
