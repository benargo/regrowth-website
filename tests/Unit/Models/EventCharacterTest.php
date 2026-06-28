<?php

namespace Tests\Unit\Models;

use App\Enums\SignupStatus;
use App\Models\Character;
use App\Models\Event;
use App\Models\EventCharacter;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\Support\ModelTestCase;

#[Group('raiding')]
#[Group('characters')]
class EventCharacterTest extends ModelTestCase
{
    use RefreshDatabase;

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
            'slot_number', 'group_number', 'signup_status', 'is_leader', 'is_loot_councillor', 'is_loot_master',
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
            'is_benched',
            'signup_status',
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
            'signup_status' => SignupStatus::class,
            'is_leader' => 'boolean',
            'is_loot_councillor' => 'boolean',
            'is_loot_master' => 'boolean',
        ]);
    }

    #[Test]
    public function signup_status_defaults_to_unconfirmed(): void
    {
        $pivot = new EventCharacter;

        $this->assertSame(SignupStatus::Unconfirmed, $pivot->signup_status);
    }

    #[Test]
    public function boolean_flags_default_to_false(): void
    {
        $pivot = new EventCharacter;

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
        $pivot = $this->createPivot(['signup_status' => SignupStatus::Confirmed->value]);

        $this->assertSame(SignupStatus::Confirmed, $pivot->signup_status);
    }

    #[Test]
    public function it_can_be_set_as_cancelled(): void
    {
        $pivot = $this->createPivot(['signup_status' => SignupStatus::Cancelled->value]);

        $this->assertSame(SignupStatus::Cancelled, $pivot->signup_status);
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

    #[Test]
    public function it_hides_created_at(): void
    {
        $pivot = new EventCharacter;

        $this->assertContains('created_at', $pivot->getHidden());
    }

    #[Test]
    public function it_does_not_hide_updated_at(): void
    {
        $pivot = new EventCharacter;

        $this->assertNotContains('updated_at', $pivot->getHidden());
    }

    // ==================== event ====================

    #[Test]
    public function event_method_returns_belongs_to(): void
    {
        $returnType = (new ReflectionMethod(EventCharacter::class, 'event'))->getReturnType();

        $this->assertSame(BelongsTo::class, $returnType->getName());
    }

    #[Test]
    public function event_relation_returns_the_associated_event(): void
    {
        $pivot = $this->createPivot();

        $eventCharacter = EventCharacter::where('event_id', $pivot->event_id)->first();

        $this->assertInstanceOf(Event::class, $eventCharacter->event);
        $this->assertSame($pivot->event_id, $eventCharacter->event->id);
    }

    // ==================== character ====================

    #[Test]
    public function character_method_returns_belongs_to(): void
    {
        $returnType = (new ReflectionMethod(EventCharacter::class, 'character'))->getReturnType();

        $this->assertSame(BelongsTo::class, $returnType->getName());
    }

    #[Test]
    public function character_relation_returns_the_associated_character(): void
    {
        $pivot = $this->createPivot();

        $eventCharacter = EventCharacter::where('character_id', $pivot->character_id)->first();

        $this->assertInstanceOf(Character::class, $eventCharacter->character);
        $this->assertSame($pivot->character_id, $eventCharacter->character->id);
    }
}
