<?php

namespace Tests\Unit\Models;

use App\Data\PlannedAbsenceReasonData;
use App\Enums\PlannedAbsenceReason;
use App\Models\Character;
use App\Models\PlannedAbsence;
use App\Models\User;
use App\Observers\PlannedAbsenceObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('raiding')]
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
            'notes',
            'created_by',
        ]);
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $model = new PlannedAbsence;

        $this->assertFillableAttribute($model, [
            'character_id',
            'user_id',
            'start_date',
            'end_date',
            'reason',
            'notes',
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
            'reason' => PlannedAbsenceReasonData::class,
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
    public function it_casts_reason_through_the_reason_cast(): void
    {
        $absence = $this->create(['reason' => PlannedAbsenceReason::Work]);

        $this->assertSame(PlannedAbsenceReason::Work, $absence->fresh()->reason->key);
    }

    #[Test]
    public function notes_are_nullable(): void
    {
        $absence = $this->create(['notes' => null]);

        $this->assertNull($absence->fresh()->notes);
    }

    #[Test]
    public function a_row_inserted_without_a_reason_defaults_to_other(): void
    {
        $absence = $this->create();
        DB::table('planned_absences')->where('id', $absence->id)->update(['reason' => DB::raw('DEFAULT(reason)')]);

        $this->assertSame(PlannedAbsenceReason::DEFAULT, $absence->fresh()->reason->key);
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

    // ==================== scopes ====================

    #[Test]
    public function ended_scope_includes_only_absences_that_ended_before_today(): void
    {
        $endedYesterday = $this->create(['start_date' => today()->subDays(3), 'end_date' => today()->subDay()]);
        $openEndedPast = $this->create(['start_date' => today()->subDay(), 'end_date' => null]);
        $endsToday = $this->create(['start_date' => today()->subDays(3), 'end_date' => today()]);
        $upcoming = $this->create(['start_date' => today()->addDay(), 'end_date' => today()->addDays(2)]);

        $ids = PlannedAbsence::ended()->pluck('id');

        $this->assertEquals([$endedYesterday->id], $ids->all());
        $this->assertNotContains($openEndedPast->id, $ids);
        $this->assertNotContains($endsToday->id, $ids);
        $this->assertNotContains($upcoming->id, $ids);
    }

    #[Test]
    public function not_ended_scope_includes_current_upcoming_and_open_ended_absences(): void
    {
        $endedYesterday = $this->create(['start_date' => today()->subDays(3), 'end_date' => today()->subDay()]);
        $endsToday = $this->create(['start_date' => today()->subDays(3), 'end_date' => today()]);
        $openEndedPast = $this->create(['start_date' => today()->subMonth(), 'end_date' => null]);
        $upcoming = $this->create(['start_date' => today()->addDay(), 'end_date' => today()->addDays(2)]);

        $ids = PlannedAbsence::notEnded()->pluck('id');

        $this->assertEqualsCanonicalizing([$endsToday->id, $openEndedPast->id, $upcoming->id], $ids->all());
        $this->assertNotContains($endedYesterday->id, $ids);
    }

    #[Test]
    public function upcoming_scope_includes_absences_starting_today_or_later_in_start_order(): void
    {
        $later = $this->create(['start_date' => today()->addDays(5), 'end_date' => today()->addDays(6)]);
        $today = $this->create(['start_date' => today(), 'end_date' => today()->addDay()]);
        $this->create(['start_date' => today()->subDay(), 'end_date' => today()->addDay()]);

        $this->assertSame([$today->id, $later->id], PlannedAbsence::upcoming()->pluck('id')->all());
    }

    // ==================== covers ====================

    #[Test]
    public function covers_includes_dates_from_start_to_end_inclusive_ignoring_time_of_day(): void
    {
        $absence = new PlannedAbsence(['start_date' => '2026-03-15 14:30:00', 'end_date' => '2026-03-20 09:00:00']);

        $this->assertTrue($absence->covers(Carbon::parse('2026-03-15 08:00:00')));
        $this->assertTrue($absence->covers(Carbon::parse('2026-03-17 12:00:00')));
        $this->assertTrue($absence->covers(Carbon::parse('2026-03-20 23:59:59')));
    }

    #[Test]
    public function covers_excludes_dates_outside_the_range(): void
    {
        $absence = new PlannedAbsence(['start_date' => '2026-03-15', 'end_date' => '2026-03-20']);

        $this->assertFalse($absence->covers(Carbon::parse('2026-03-14 23:59:59')));
        $this->assertFalse($absence->covers(Carbon::parse('2026-03-21 00:00:00')));
    }

    #[Test]
    public function covers_includes_every_date_from_the_start_when_open_ended(): void
    {
        $absence = new PlannedAbsence(['start_date' => '2026-03-15 14:30:00', 'end_date' => null]);

        $this->assertFalse($absence->covers(Carbon::parse('2026-03-14')));
        $this->assertTrue($absence->covers(Carbon::parse('2026-03-15 08:00:00')));
        $this->assertTrue($absence->covers(Carbon::parse('2030-01-01')));
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
