<?php

namespace Tests\Unit\Models;

use App\Events\PlannedAbsenceCreated;
use App\Events\PlannedAbsenceDeleted;
use App\Events\PlannedAbsenceUpdated;
use App\Models\Character;
use App\Models\PlannedAbsence;
use App\Models\User;
use App\Observers\PlannedAbsenceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class PlannedAbsenceTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return PlannedAbsence::class;
    }

    #[Test]
    public function it_uses_uuids(): void
    {
        $model = new PlannedAbsence;

        $this->assertContains(HasUuids::class, class_uses_recursive($model));
        $this->assertSame('string', $model->getKeyType());
        $this->assertFalse($model->getIncrementing());
    }

    #[Test]
    public function factory_generates_uuid_for_id(): void
    {
        $absence = $this->create();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $absence->id
        );
    }

    #[Test]
    public function it_uses_planned_absences_table(): void
    {
        $model = new PlannedAbsence;

        $this->assertSame('planned_absences', $model->getTable());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new PlannedAbsence;

        $this->assertFillable($model, [
            'character_id',
            'user_id',
            'start_date',
            'end_date',
            'reason',
            'discord_message_id',
            'created_by',
        ]);
    }

    #[Test]
    public function it_casts_dates_as_datetime(): void
    {
        $model = new PlannedAbsence;

        $this->assertCasts($model, [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ]);
    }

    #[Test]
    public function it_uses_soft_deletes(): void
    {
        $model = new PlannedAbsence;

        $this->assertContains(SoftDeletes::class, class_uses_recursive($model));
    }

    #[Test]
    public function it_belongs_to_character_when_set(): void
    {
        $character = Character::factory()->create();
        $absence = $this->create(['character_id' => $character->id]);

        $this->assertRelation($absence, 'character', BelongsTo::class);
        $this->assertTrue($absence->character->is($character));
    }

    #[Test]
    public function it_belongs_to_created_by_user(): void
    {
        $user = User::factory()->create();
        $absence = $this->create(['created_by' => $user->id]);

        $this->assertRelation($absence, 'createdBy', BelongsTo::class);
        $this->assertTrue($absence->createdBy->is($user));
    }

    #[Test]
    public function it_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $absence = $this->create(['user_id' => $user->id]);

        $this->assertRelation($absence, 'user', BelongsTo::class);
        $this->assertTrue($absence->user->is($user));
    }

    #[Test]
    public function user_id_is_nullable(): void
    {
        $absence = $this->create(['user_id' => null]);

        $this->assertNull($absence->user_id);
        $this->assertNull($absence->user);
    }

    #[Test]
    public function factory_without_user_state_sets_user_id_to_null(): void
    {
        $absence = $this->factory()->withoutUser()->create();

        $this->assertNull($absence->user_id);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $absence = $this->create();

        $this->assertNotNull($absence->character_id);
        $this->assertNotNull($absence->user_id);
        $this->assertNotNull($absence->start_date);
        $this->assertNotNull($absence->end_date);
        $this->assertNotNull($absence->reason);
        $this->assertNotNull($absence->created_by);
        $this->assertModelExists($absence);
    }

    #[Test]
    public function factory_with_character_state_sets_character_id(): void
    {
        $absence = $this->factory()->withCharacter()->create();

        $this->assertNotNull($absence->character_id);
        $this->assertInstanceOf(Character::class, $absence->character);
    }

    #[Test]
    public function factory_without_end_date_state_sets_end_date_to_null(): void
    {
        $absence = $this->factory()->withoutEndDate()->create();

        $this->assertNull($absence->end_date);
    }

    #[Test]
    public function discord_message_id_is_nullable_by_default(): void
    {
        $absence = $this->create();

        $this->assertNull($absence->discord_message_id);
    }

    #[Test]
    public function factory_with_discord_message_id_state_sets_discord_message_id(): void
    {
        $absence = $this->factory()->withDiscordMessageId()->create();

        $this->assertNotNull($absence->discord_message_id);
    }

    #[Test]
    public function it_can_be_soft_deleted(): void
    {
        $absence = $this->create();

        $absence->delete();

        $this->assertSoftDeleted($absence);
    }

    #[Test]
    public function it_can_be_restored_after_soft_delete(): void
    {
        $absence = $this->create();
        $absence->delete();

        $absence->restore();

        $this->assertModelExists($absence);
        $this->assertNull($absence->deleted_at);
    }

    // ==================== serializeDate ====================

    #[Test]
    public function serialize_date_formats_dates_as_y_m_d(): void
    {
        $absence = $this->create([
            'start_date' => '2026-03-15 14:30:00',
            'end_date' => '2026-03-20 09:00:00',
        ]);

        $array = $absence->toArray();

        $this->assertSame('2026-03-15', $array['start_date']);
        $this->assertSame('2026-03-20', $array['end_date']);
    }

    // ==================== observer ====================

    #[Test]
    public function it_is_observed_by_planned_absence_observer(): void
    {
        $attributes = (new \ReflectionClass(PlannedAbsence::class))
            ->getAttributes(ObservedBy::class);

        $this->assertNotEmpty($attributes);

        $observerClasses = $attributes[0]->getArguments()[0];
        $this->assertContains(PlannedAbsenceObserver::class, $observerClasses);
    }
}
