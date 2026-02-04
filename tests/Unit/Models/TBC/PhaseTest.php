<?php

namespace Tests\Unit\Models\TBC;

use App\Models\TBC\Boss;
use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class PhaseTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Phase::class;
    }

    #[Test]
    public function it_uses_tbc_phases_table(): void
    {
        $model = new Phase;

        $this->assertSame('tbc_phases', $model->getTable());
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

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $phase->start_date);
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

    #[Test]
    public function it_has_many_raids(): void
    {
        $phase = $this->create();
        Raid::factory()->count(3)->create(['phase_id' => $phase->id]);

        $this->assertRelation($phase, 'raids', HasMany::class);
        $this->assertCount(3, $phase->raids);
    }

    #[Test]
    public function it_has_many_bosses_through_raids(): void
    {
        $phase = $this->create();
        $raid1 = Raid::factory()->create(['phase_id' => $phase->id]);
        $raid2 = Raid::factory()->create(['phase_id' => $phase->id]);
        Boss::factory()->count(2)->create(['raid_id' => $raid1->id]);
        Boss::factory()->count(3)->create(['raid_id' => $raid2->id]);

        $this->assertRelation($phase, 'bosses', HasManyThrough::class);
        $this->assertCount(5, $phase->bosses);
    }

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

    #[Test]
    public function it_has_many_guild_tags(): void
    {
        $phase = $this->create();
        GuildTag::factory()->count(3)->create(['tbc_phase_id' => $phase->id]);

        $this->assertRelation($phase, 'guildTags', HasMany::class);
        $this->assertCount(3, $phase->guildTags);
    }
}
