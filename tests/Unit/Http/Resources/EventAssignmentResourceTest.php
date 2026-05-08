<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\CharacterResource;
use App\Http\Resources\EventAssignmentResource;
use App\Http\Resources\SpellResource;
use App\Http\Resources\TargetMarkerResource;
use App\Models\Character;
use App\Models\EventAssignment;
use App\Models\Spell;
use App\Models\TargetMarker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventAssignmentResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $assignment = EventAssignment::factory()->create();

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('event_id', $array);
        $this->assertArrayHasKey('boss_id', $array);
        $this->assertArrayHasKey('label', $array);
        $this->assertArrayHasKey('sort_order', $array);
        $this->assertArrayHasKey('left', $array);
        $this->assertArrayHasKey('right', $array);
    }

    #[Test]
    public function it_returns_scalar_fields(): void
    {
        $assignment = EventAssignment::factory()
            ->withLeftCustom('Group 1')
            ->withRightCustom('Tank')
            ->create(['label' => 'Role', 'sort_order' => 5]);

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertSame($assignment->id, $array['id']);
        $this->assertSame($assignment->event_id, $array['event_id']);
        $this->assertNull($array['boss_id']);
        $this->assertSame('Role', $array['label']);
        $this->assertSame(5, $array['sort_order']);
    }

    #[Test]
    public function it_returns_null_label_when_not_set(): void
    {
        $assignment = EventAssignment::factory()->create(['label' => null]);

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertNull($array['label']);
    }

    #[Test]
    public function it_returns_boss_id_when_scoped_to_boss(): void
    {
        $assignment = EventAssignment::factory()->create();
        $assignment->boss_id = 42;

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertSame(42, $array['boss_id']);
    }

    // ============ Left side resolution ============

    #[Test]
    public function it_resolves_left_character_to_character_resource(): void
    {
        $character = Character::factory()->create();
        $assignment = EventAssignment::factory()->withLeftCharacter($character)->create();

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertInstanceOf(CharacterResource::class, $array['left']);
        $this->assertSame($character->id, $array['left']->resource->id);
    }

    #[Test]
    public function it_resolves_left_spell_to_spell_resource(): void
    {
        $spell = Spell::factory()->create();
        $assignment = EventAssignment::factory()->withLeftSpell($spell)->create();

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertInstanceOf(SpellResource::class, $array['left']);
        $this->assertSame($spell->id, $array['left']->resource->id);
    }

    #[Test]
    public function it_resolves_left_primitive_to_raw_string(): void
    {
        $assignment = EventAssignment::factory()->withLeftCustom('Group 1')->create();

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertSame('Group 1', $array['left']);
    }

    #[Test]
    public function it_resolves_left_group_number_to_raw_string(): void
    {
        $assignment = EventAssignment::factory()->withLeftGroupNumber(3)->create();

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertSame('3', $array['left']);
    }

    // ============ Right side resolution ============

    #[Test]
    public function it_resolves_right_character_to_character_resource(): void
    {
        $character = Character::factory()->create();
        $assignment = EventAssignment::factory()->withRightCharacter($character)->create();

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertInstanceOf(CharacterResource::class, $array['right']);
        $this->assertSame($character->id, $array['right']->resource->id);
    }

    #[Test]
    public function it_resolves_right_target_marker_to_target_marker_resource(): void
    {
        $marker = TargetMarker::factory()->create();
        $assignment = EventAssignment::factory()->withRightTargetMarker($marker)->create();

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertInstanceOf(TargetMarkerResource::class, $array['right']);
        $this->assertSame($marker->slug, $array['right']->resource->slug);
    }

    #[Test]
    public function it_resolves_right_spell_to_spell_resource(): void
    {
        $spell = Spell::factory()->create();
        $assignment = EventAssignment::factory()->withRightSpell($spell)->create();

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertInstanceOf(SpellResource::class, $array['right']);
        $this->assertSame($spell->id, $array['right']->resource->id);
    }

    #[Test]
    public function it_resolves_right_primitive_to_raw_string(): void
    {
        $assignment = EventAssignment::factory()->withRightCustom('kick rotation A')->create();

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertSame('kick rotation A', $array['right']);
    }

    #[Test]
    public function it_resolves_right_group_range_to_raw_string(): void
    {
        $assignment = EventAssignment::factory()->withRightGroupRange('1-3')->create();

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertSame('1-3', $array['right']);
    }
}
