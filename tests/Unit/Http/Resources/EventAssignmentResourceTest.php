<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\EventAssignmentResource;
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
        $this->assertArrayHasKey('sort_order', $array);
        $this->assertArrayHasKey('left', $array);
        $this->assertArrayHasKey('right', $array);
        $this->assertArrayNotHasKey('label', $array);
        $this->assertArrayNotHasKey('event_id', $array);
        $this->assertArrayNotHasKey('boss_id', $array);
    }

    #[Test]
    public function it_returns_scalar_fields(): void
    {
        $assignment = EventAssignment::factory()
            ->withLeftCustom('Group 1')
            ->withRightCustom('Tank')
            ->create(['sort_order' => 5]);

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertSame($assignment->id, $array['id']);
        $this->assertSame(5, $array['sort_order']);
    }

    // ============ Left side resolution ============

    #[Test]
    public function it_resolves_left_character_to_character_resource(): void
    {
        $character = Character::factory()->create();
        $assignment = EventAssignment::factory()->withLeftCharacter($character)->create();

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertIsArray($array['left']);
        $this->assertSame('character', $array['left']['type']);
        $this->assertIsArray($array['left']['data']);
        $this->assertSame($character->id, $array['left']['data']['id']);
    }

    #[Test]
    public function it_resolves_left_spell_to_spell_resource(): void
    {
        $spell = Spell::factory()->create();
        $assignment = EventAssignment::factory()->withLeftSpell($spell)->create();

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertIsArray($array['left']);
        $this->assertSame('spell', $array['left']['type']);
        $this->assertIsArray($array['left']['data']);
        $this->assertSame($spell->id, $array['left']['data']['id']);
    }

    #[Test]
    public function it_resolves_left_primitive_to_raw_string(): void
    {
        $assignment = EventAssignment::factory()->withLeftCustom('Group 1')->create();

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertIsArray($array['left']);
        $this->assertSame('string', $array['left']['type']);
        $this->assertSame('Group 1', $array['left']['data']);
    }

    #[Test]
    public function it_resolves_left_group_number_to_raw_string(): void
    {
        $assignment = EventAssignment::factory()->withLeftGroupNumber(3)->create();

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertIsArray($array['left']);
        $this->assertSame('string', $array['left']['type']);
        $this->assertSame('3', $array['left']['data']);
    }

    // ============ Right side resolution ============

    #[Test]
    public function it_resolves_right_character_to_character_resource(): void
    {
        $character = Character::factory()->create();
        $assignment = EventAssignment::factory()->withRightCharacter($character)->create();

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertIsArray($array['right']);
        $this->assertSame('character', $array['right']['type']);
        $this->assertIsArray($array['right']['data']);
        $this->assertSame($character->id, $array['right']['data']['id']);
    }

    #[Test]
    public function it_resolves_right_target_marker_to_target_marker_resource(): void
    {
        $marker = TargetMarker::factory()->create();
        $assignment = EventAssignment::factory()->withRightTargetMarker($marker)->create();

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertIsArray($array['right']);
        $this->assertSame('target_marker', $array['right']['type']);
        $this->assertIsArray($array['right']['data']);
        $this->assertSame($marker->slug, $array['right']['data']['slug']);
    }

    #[Test]
    public function it_resolves_right_spell_to_spell_resource(): void
    {
        $spell = Spell::factory()->create();
        $assignment = EventAssignment::factory()->withRightSpell($spell)->create();

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertIsArray($array['right']);
        $this->assertSame('spell', $array['right']['type']);
        $this->assertIsArray($array['right']['data']);
        $this->assertSame($spell->id, $array['right']['data']['id']);
    }

    #[Test]
    public function it_resolves_right_primitive_to_raw_string(): void
    {
        $assignment = EventAssignment::factory()->withRightCustom('kick rotation A')->create();

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertIsArray($array['right']);
        $this->assertSame('string', $array['right']['type']);
        $this->assertSame('kick rotation A', $array['right']['data']);
    }

    #[Test]
    public function it_resolves_right_group_range_to_raw_string(): void
    {
        $assignment = EventAssignment::factory()->withRightGroupRange('1-3')->create();

        $array = (new EventAssignmentResource($assignment))->toArray(new Request);

        $this->assertIsArray($array['right']);
        $this->assertSame('string', $array['right']['type']);
        $this->assertSame('1-3', $array['right']['data']);
    }
}
