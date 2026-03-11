<?php

namespace Tests\Unit\Models;

use App\Events\PlannedAbsenceDeleted;
use App\Events\PlannedAbsenceSaved;
use App\Models\Character;
use App\Models\PlannedAbsence;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Prunable;
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
            'start_date',
            'end_date',
            'reason',
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
    public function it_uses_prunable(): void
    {
        $model = new PlannedAbsence;

        $this->assertContains(Prunable::class, class_uses_recursive($model));
    }

    #[Test]
    public function prunable_excludes_records_without_end_date(): void
    {
        $this->create(['end_date' => null]);

        $prunable = PlannedAbsence::withTrashed()
            ->whereIn('id', PlannedAbsence::make()->prunable()->select('id'))
            ->get();

        $this->assertCount(0, $prunable);
    }

    #[Test]
    public function prunable_excludes_records_with_recent_end_date(): void
    {
        $this->create(['end_date' => now()->subWeek()]);

        $prunable = PlannedAbsence::withTrashed()
            ->whereIn('id', PlannedAbsence::make()->prunable()->select('id'))
            ->get();

        $this->assertCount(0, $prunable);
    }

    #[Test]
    public function prunable_includes_records_with_end_date_older_than_one_month(): void
    {
        $absence = $this->create(['end_date' => now()->subMonth()->subDay()]);

        $prunable = PlannedAbsence::withTrashed()
            ->whereIn('id', PlannedAbsence::make()->prunable()->select('id'))
            ->get();

        $this->assertCount(1, $prunable);
        $this->assertTrue($prunable->first()->is($absence));
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
    public function it_has_null_character_when_character_id_is_null(): void
    {
        $absence = $this->create(['character_id' => null]);

        $this->assertNull($absence->character);
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
    public function factory_creates_valid_model(): void
    {
        $absence = $this->create();

        $this->assertNotNull($absence->start_date);
        $this->assertNotNull($absence->end_date);
        $this->assertNotNull($absence->reason);
        $this->assertNotNull($absence->created_by);
        $this->assertNull($absence->character_id);
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

    #[Test]
    public function it_dispatches_planned_absence_saved_event_on_create(): void
    {
        Event::fake();

        $this->create();

        Event::assertDispatched(PlannedAbsenceSaved::class);
    }

    #[Test]
    public function it_dispatches_planned_absence_saved_event_on_update(): void
    {
        $absence = $this->create();
        Event::fake();

        $absence->update(['reason' => 'Updated reason']);

        Event::assertDispatched(PlannedAbsenceSaved::class);
    }

    #[Test]
    public function it_dispatches_planned_absence_deleted_event_on_delete(): void
    {
        $absence = $this->create();
        Event::fake();

        $absence->delete();

        Event::assertDispatched(PlannedAbsenceDeleted::class);
    }
}
