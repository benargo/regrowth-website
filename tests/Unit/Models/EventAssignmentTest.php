<?php

namespace Tests\Unit\Models;

use App\Models\Boss;
use App\Models\Character;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\EventAssignmentGroup;
use App\Models\Spell;
use App\Models\TargetMarker;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class EventAssignmentTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return EventAssignment::class;
    }

    // ============ Schema / config ============

    #[Test]
    public function it_uses_event_assignments_table(): void
    {
        $model = new EventAssignment;

        $this->assertSame('event_assignments', $model->getTable());
    }

    #[Test]
    public function it_uses_auto_incrementing_integer_primary_key(): void
    {
        $model = new EventAssignment;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
        $this->assertSame('int', $model->getKeyType());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new EventAssignment;

        $this->assertFillable($model, [
            'event_id',
            'boss_id',
            'group_id',
            'sort_order',
            'left_model_key',
            'left_value',
            'right_model_key',
            'right_value',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new EventAssignment;

        $this->assertCasts($model, ['sort_order' => 'integer']);
    }

    #[Test]
    public function it_has_timestamps(): void
    {
        $model = new EventAssignment;

        $this->assertTrue($model->usesTimestamps());
    }

    // ============ Factory / persistence ============

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $assignment = $this->create();

        $this->assertModelExists($assignment);
        $this->assertNotNull($assignment->event_id);
        $this->assertSame('character', $assignment->left_model_key);
        $this->assertNotEmpty($assignment->left_value);
        $this->assertNull($assignment->right_model_key);
        $this->assertNotEmpty($assignment->right_value);
    }

    #[Test]
    public function it_can_be_created_without_a_boss(): void
    {
        $assignment = $this->create(['boss_id' => null]);

        $this->assertModelExists($assignment);
        $this->assertNull($assignment->boss_id);
    }

    #[Test]
    public function it_can_be_created_with_a_boss(): void
    {
        $boss = Boss::factory()->create();
        $assignment = $this->create(['boss_id' => $boss->id]);

        $this->assertModelExists($assignment);
        $this->assertSame($boss->id, $assignment->boss_id);
    }

    // ============ Left-side column variants ============

    #[Test]
    public function it_supports_left_character(): void
    {
        $character = Character::factory()->create();
        $assignment = $this->factory()->withLeftCharacter($character)->create();

        $this->assertSame('character', $assignment->left_model_key);
        $this->assertSame((string) $character->id, $assignment->left_value);
    }

    #[Test]
    public function it_supports_left_spell(): void
    {
        $spell = Spell::factory()->create();
        $assignment = $this->factory()->withLeftSpell($spell)->create();

        $this->assertSame('spell', $assignment->left_model_key);
        $this->assertSame((string) $spell->id, $assignment->left_value);
    }

    #[Test]
    public function it_supports_left_group_number(): void
    {
        $assignment = $this->factory()->withLeftGroupNumber(3)->create();

        $this->assertNull($assignment->left_model_key);
        $this->assertSame('3', $assignment->left_value);
    }

    #[Test]
    public function it_supports_left_custom_label(): void
    {
        $assignment = $this->factory()->withLeftCustom('All mages')->create();

        $this->assertNull($assignment->left_model_key);
        $this->assertSame('All mages', $assignment->left_value);
    }

    // ============ Right-side column variants ============

    #[Test]
    public function it_supports_right_character(): void
    {
        $character = Character::factory()->create();
        $assignment = $this->factory()->withRightCharacter($character)->create();

        $this->assertSame('character', $assignment->right_model_key);
        $this->assertSame((string) $character->id, $assignment->right_value);
    }

    #[Test]
    public function it_supports_right_target_marker(): void
    {
        $marker = TargetMarker::factory()->create();
        $assignment = $this->factory()->withRightTargetMarker($marker)->create();

        $this->assertSame('target_marker', $assignment->right_model_key);
        $this->assertSame($marker->slug, $assignment->right_value);
    }

    #[Test]
    public function it_supports_right_spell(): void
    {
        $spell = Spell::factory()->create();
        $assignment = $this->factory()->withRightSpell($spell)->create();

        $this->assertSame('spell', $assignment->right_model_key);
        $this->assertSame((string) $spell->id, $assignment->right_value);
    }

    #[Test]
    public function it_supports_right_group_range(): void
    {
        $assignment = $this->factory()->withRightGroupRange('Groups 1-3')->create();

        $this->assertNull($assignment->right_model_key);
        $this->assertSame('Groups 1-3', $assignment->right_value);
    }

    #[Test]
    public function it_supports_right_custom_label(): void
    {
        $assignment = $this->factory()->withRightCustom('kick rotation A')->create();

        $this->assertNull($assignment->right_model_key);
        $this->assertSame('kick rotation A', $assignment->right_value);
    }

    // ============ Resolver tests ============

    #[Test]
    public function resolve_left_returns_character_model_when_model_key_is_character(): void
    {
        $character = Character::factory()->create();
        $assignment = $this->factory()->withLeftCharacter($character)->create();

        $resolved = $assignment->resolveLeft();

        $this->assertInstanceOf(Character::class, $resolved);
        $this->assertTrue($resolved->is($character));
    }

    #[Test]
    public function resolve_left_returns_spell_model_when_model_key_is_spell(): void
    {
        $spell = Spell::factory()->create();
        $assignment = $this->factory()->withLeftSpell($spell)->create();

        $resolved = $assignment->resolveLeft();

        $this->assertInstanceOf(Spell::class, $resolved);
        $this->assertTrue($resolved->is($spell));
    }

    #[Test]
    public function resolve_left_returns_raw_string_when_model_key_is_null(): void
    {
        $assignment = $this->factory()->withLeftCustom('All mages')->create();

        $resolved = $assignment->resolveLeft();

        $this->assertSame('All mages', $resolved);
    }

    #[Test]
    public function resolve_right_returns_target_marker_when_model_key_is_target_marker(): void
    {
        $marker = TargetMarker::factory()->create();
        $assignment = $this->factory()->withRightTargetMarker($marker)->create();

        $resolved = $assignment->resolveRight();

        $this->assertInstanceOf(TargetMarker::class, $resolved);
        $this->assertSame($marker->slug, $resolved->slug);
    }

    #[Test]
    public function resolve_right_returns_raw_string_when_model_key_is_null(): void
    {
        $assignment = $this->factory()->withRightCustom('Main tank')->create();

        $resolved = $assignment->resolveRight();

        $this->assertSame('Main tank', $resolved);
    }

    // ============ Invariant ============

    #[Test]
    public function is_valid_returns_true_when_both_values_are_present(): void
    {
        $assignment = $this->create();

        $this->assertTrue($assignment->isValid());
    }

    #[Test]
    public function is_valid_returns_false_when_left_value_is_empty(): void
    {
        $assignment = $this->make(['left_value' => '']);

        $this->assertFalse($assignment->isValid());
    }

    #[Test]
    public function is_valid_returns_false_when_right_value_is_empty(): void
    {
        $assignment = $this->make(['right_value' => '']);

        $this->assertFalse($assignment->isValid());
    }

    // ============ Relationships ============

    #[Test]
    public function it_belongs_to_an_event(): void
    {
        $event = Event::factory()->create();
        $assignment = $this->create(['event_id' => $event->id]);

        $this->assertRelation($assignment, 'event', BelongsTo::class);
        $this->assertTrue($assignment->event->is($event));
    }

    #[Test]
    public function it_belongs_to_a_boss(): void
    {
        $boss = Boss::factory()->create();
        $assignment = $this->create(['boss_id' => $boss->id]);

        $relation = $assignment->boss();
        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertTrue($assignment->boss->is($boss));
    }

    #[Test]
    public function boss_is_null_when_not_scoped_to_a_boss(): void
    {
        $assignment = $this->create(['boss_id' => null]);

        $this->assertInstanceOf(BelongsTo::class, $assignment->boss());
        $this->assertNull($assignment->boss);
    }

    #[Test]
    public function it_belongs_to_a_group(): void
    {
        $group = EventAssignmentGroup::factory()->create();
        $assignment = $this->create(['group_id' => $group->id]);

        $this->assertRelation($assignment, 'group', BelongsTo::class);
        $this->assertTrue($assignment->group->is($group));
    }

    #[Test]
    public function group_is_null_when_not_assigned_to_a_group(): void
    {
        $assignment = $this->create(['group_id' => null]);

        $this->assertInstanceOf(BelongsTo::class, $assignment->group());
        $this->assertNull($assignment->group);
    }

    // ============ Cascade deletes ============

    #[Test]
    public function deleting_event_cascades_to_assignments(): void
    {
        $event = Event::factory()->create();
        $assignment = $this->create(['event_id' => $event->id]);

        $event->delete();

        $this->assertDatabaseMissing('event_assignments', ['id' => $assignment->id]);
    }

    #[Test]
    public function deleting_boss_cascades_to_assignments(): void
    {
        $boss = Boss::factory()->create();
        $assignment = $this->create(['boss_id' => $boss->id]);

        $boss->delete();

        $this->assertDatabaseMissing('event_assignments', ['id' => $assignment->id]);
    }

    // ============ Event::assignments() and Boss::assignments() ============

    #[Test]
    public function event_has_many_assignments(): void
    {
        $event = Event::factory()->create();
        $this->create(['event_id' => $event->id]);
        $this->create(['event_id' => $event->id]);

        $relation = $event->assignments();
        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertCount(2, $event->assignments);
    }

    #[Test]
    public function boss_has_many_assignments(): void
    {
        $boss = Boss::factory()->create();
        $event = Event::factory()->create();
        $this->create(['event_id' => $event->id, 'boss_id' => $boss->id]);
        $this->create(['event_id' => $event->id, 'boss_id' => $boss->id]);

        $relation = $boss->assignments();
        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertCount(2, $boss->assignments);
    }
}
