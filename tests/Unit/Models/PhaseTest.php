<?php

namespace Tests\Unit\Models;

use App\Helpers\Database\Eloquent\Relations\HasManyKeyBy;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\Report;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('raiding')]
class PhaseTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Phase::class;
    }

    #[Test]
    public function it_uses_phases_table(): void
    {
        $model = new Phase;

        $this->assertSame('phases', $model->getTable());
    }

    #[Test]
    public function it_uses_auto_incrementing_id(): void
    {
        $model = new Phase;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new Phase;

        $this->assertFillable($model, [
            'number',
            'description',
            'start_date',
            'sort_order',
            'game_version_id',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new Phase;

        $this->assertCasts($model, [
            'start_date' => 'datetime',
        ]);
    }

    // ==================== persistence ====================

    #[Test]
    public function it_can_be_created_with_required_attributes(): void
    {
        $phase = $this->create([
            'number' => 1,
            'description' => 'Phase 1: Karazhan',
        ]);

        $this->assertTableHas(['number' => 1, 'description' => 'Phase 1: Karazhan']);
        $this->assertModelExists($phase);
    }

    #[Test]
    public function it_can_be_created_with_all_attributes(): void
    {
        $startDate = now()->subMonth()->startOfSecond();

        $phase = $this->create([
            'number' => 2,
            'description' => 'Phase 2: Gruul & Magtheridon',
            'start_date' => $startDate,
        ]);

        $this->assertTableHas([
            'number' => 2,
            'description' => 'Phase 2: Gruul & Magtheridon',
        ]);
        $this->assertModelExists($phase);
        $this->assertSame($startDate->format('Y-m-d H:i:s'), $phase->start_date->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_casts_start_date_to_datetime(): void
    {
        $phase = $this->create([
            'description' => 'Test Phase',
            'start_date' => '2024-06-15 10:00:00',
        ]);

        $this->assertInstanceOf(Carbon::class, $phase->start_date);
        $this->assertSame('2024-06-15 10:00:00', $phase->start_date->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_allows_null_start_date(): void
    {
        $phase = $this->create([
            'description' => 'Unscheduled Phase',
            'start_date' => null,
        ]);

        $this->assertNull($phase->start_date);
        $this->assertModelExists($phase);
    }

    // ==================== factory states ====================

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $phase = $this->create();

        $this->assertNotEmpty($phase->description);
        $this->assertModelExists($phase);
    }

    #[Test]
    public function factory_started_state_sets_past_start_date(): void
    {
        $phase = $this->factory()->started()->create();

        $this->assertNotNull($phase->start_date);
        $this->assertTrue($phase->start_date->isPast());
    }

    #[Test]
    public function factory_upcoming_state_sets_future_start_date(): void
    {
        $phase = $this->factory()->upcoming()->create();

        $this->assertNotNull($phase->start_date);
        $this->assertTrue($phase->start_date->isFuture());
    }

    #[Test]
    public function factory_unscheduled_state_sets_null_start_date(): void
    {
        $phase = $this->factory()->unscheduled()->create();

        $this->assertNull($phase->start_date);
    }

    #[Test]
    public function factory_for_game_version_state_sets_game_version_id(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $phase = $this->factory()->forGameVersion($gameVersion)->create();

        $this->assertSame($gameVersion->id, $phase->game_version_id);
    }

    #[Test]
    public function factory_default_game_version_id_is_null(): void
    {
        $phase = $this->create();

        $this->assertNull($phase->game_version_id);
    }

    // ==================== number accessor ====================

    #[Test]
    public function number_accessor_returns_int_for_whole_numbers(): void
    {
        $phase = $this->create(['number' => 1.0]);

        $this->assertIsInt($phase->number);
        $this->assertSame(1, $phase->number);
    }

    #[Test]
    public function number_accessor_returns_float_for_decimal_numbers(): void
    {
        $phase = $this->create(['number' => 2.5]);

        $this->assertIsFloat($phase->number);
        $this->assertSame(2.5, $phase->number);
    }

    // ==================== relationships ====================

    #[Test]
    public function it_has_many_reports(): void
    {
        $phase = $this->create();
        $report = Report::factory()->create();
        Report::whereKey($report->id)->update(['phase_id' => $phase->id]);

        $this->assertRelation($phase, 'reports', HasMany::class);
        $this->assertTrue($phase->reports->sole()->is($report));
    }

    #[Test]
    public function it_has_many_raids(): void
    {
        $phase = $this->create();
        Raid::factory()->count(3)->create(['phase_id' => $phase->id]);

        $this->assertRelation($phase, 'raids', HasManyKeyBy::class);
        $this->assertCount(3, $phase->raids);
    }

    #[Test]
    public function raids_are_keyed_by_id(): void
    {
        $phase = $this->create();
        $raids = Raid::factory()->count(3)->create(['phase_id' => $phase->id]);

        $raidIds = $raids->pluck('id');
        $this->assertEquals($raidIds->sort()->values(), $phase->raids->keys()->sort()->values());
    }

    // ==================== has started ====================

    #[Test]
    public function has_started_returns_true_when_start_date_is_in_the_past(): void
    {
        $phase = $this->factory()->started()->create();

        $this->assertTrue($phase->hasStarted());
    }

    #[Test]
    public function has_started_returns_false_when_start_date_is_in_the_future(): void
    {
        $phase = $this->factory()->upcoming()->create();

        $this->assertFalse($phase->hasStarted());
    }

    #[Test]
    public function has_started_returns_false_when_start_date_is_null(): void
    {
        $phase = $this->factory()->unscheduled()->create();

        $this->assertFalse($phase->hasStarted());
    }

    // ==================== started ====================

    #[Test]
    public function started_scope_keeps_phases_that_started_by_now(): void
    {
        $this->freezeTime();
        $started = Phase::factory()->create(['start_date' => now()->subDay()]);
        $startingNow = Phase::factory()->create(['start_date' => now()]);
        Phase::factory()->upcoming()->create();
        Phase::factory()->unscheduled()->create();

        $this->assertEqualsCanonicalizing([$started->id, $startingNow->id], Phase::started()->pluck('id')->all());
    }

    // ==================== order ====================

    #[Test]
    public function in_release_order_lists_dated_phases_by_start_date_then_undated_by_sort_order(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $laterUndated = Phase::factory()->for($gameVersion)->unscheduled()->create(['number' => 4]);
        $earlierUndated = Phase::factory()->for($gameVersion)->unscheduled()->create(['number' => 5]);
        $laterUndated->update(['sort_order' => 2]);
        $earlierUndated->update(['sort_order' => 1]);
        $later = Phase::factory()->for($gameVersion)->create(['number' => 1, 'start_date' => '2026-05-15 00:00:00']);
        $earlier = Phase::factory()->for($gameVersion)->create(['number' => 3, 'start_date' => '2026-01-01 00:00:00']);

        $this->assertSame(
            [$earlier->id, $later->id, $earlierUndated->id, $laterUndated->id],
            Phase::inReleaseOrder()->pluck('id')->all(),
        );
    }

    #[Test]
    public function a_new_unscheduled_phase_goes_to_the_end_of_its_game_versions_list(): void
    {
        $gameVersion = GameVersion::factory()->create();
        Phase::factory()->for($gameVersion)->unscheduled()->create(['number' => 4]);
        Phase::factory()->for(GameVersion::factory())->unscheduled()->create(['number' => 4]);

        $phase = Phase::factory()->for($gameVersion)->unscheduled()->create(['number' => 5]);

        $this->assertSame(2, $phase->sort_order);
    }

    #[Test]
    public function name_reads_phase_and_its_number(): void
    {
        $this->assertSame('Phase 3.5', Phase::factory()->make(['number' => '3.5'])->name);
        $this->assertSame('Phase 2', Phase::factory()->make(['number' => '2.0'])->name);
    }

    // ==================== usage ====================

    #[Test]
    public function it_is_in_use_while_it_has_raids(): void
    {
        $phase = Phase::factory()->create();
        $this->assertFalse($phase->isInUse());

        Raid::factory()->for($phase)->create();

        $this->assertTrue($phase->isInUse());
    }

    #[Test]
    public function it_is_in_use_while_reports_fall_in_it(): void
    {
        $phase = Phase::factory()->create();
        Report::withoutEvents(fn () => Report::factory()->create(['phase_id' => $phase->id]));

        $this->assertTrue($phase->isInUse());
    }

    #[Test]
    public function with_usage_counts_counts_raids_and_reports(): void
    {
        $phase = Phase::factory()->create();
        Raid::factory()->for($phase)->count(2)->create();

        $counted = Phase::withUsageCounts()->find($phase->id);

        $this->assertSame(2, $counted->raids_count);
        $this->assertSame(0, $counted->reports_count);
    }

    // ==================== game version ====================

    #[Test]
    public function it_belongs_to_a_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = $this->create(['game_version_id' => $gameVersion->id]);

        $this->assertRelation($phase, 'gameVersion', BelongsTo::class);
        $this->assertTrue($phase->gameVersion->is($gameVersion));
    }
}
