<?php

namespace Tests\Unit\Models\WarcraftLogs;

use App\Events\AddonSettingsProcessed;
use App\Models\Character;
use App\Models\WarcraftLogs\Report;
use App\Services\WarcraftLogs\Data\Zone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class ReportTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Report::class;
    }

    #[Test]
    public function it_uses_wcl_reports_table(): void
    {
        $report = new Report;

        $this->assertSame('wcl_reports', $report->getTable());
    }

    #[Test]
    public function it_has_string_primary_key(): void
    {
        $report = new Report;

        $this->assertSame('code', $report->getKeyName());
        $this->assertFalse($report->getIncrementing());
        $this->assertSame('string', $report->getKeyType());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $report = new Report;

        $this->assertFillable($report, [
            'code',
            'title',
            'start_time',
            'end_time',
            'zone_id',
            'zone_name',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $report = new Report;

        $this->assertCasts($report, [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'zone_id' => 'integer',
        ]);
    }

    #[Test]
    public function it_hides_correct_attributes(): void
    {
        $report = new Report;

        $this->assertHidden($report, [
            'created_at',
            'updated_at',
            'zone_id',
            'zone_name',
        ]);
    }

    #[Test]
    public function it_can_be_created_with_factory(): void
    {
        $report = $this->create();

        $this->assertTableHas([
            'code' => $report->code,
        ]);
    }

    #[Test]
    public function it_casts_dates_correctly(): void
    {
        $report = $this->create([
            'start_time' => '2026-01-15 20:00:00',
            'end_time' => '2026-01-15 23:30:00',
        ]);

        $report->refresh();

        $this->assertInstanceOf(Carbon::class, $report->start_time);
        $this->assertInstanceOf(Carbon::class, $report->end_time);
    }

    #[Test]
    public function it_casts_zone_id_to_integer(): void
    {
        $report = $this->factory()->withZone(1047, 'Karazhan')->create();

        $report->refresh();

        $this->assertIsInt($report->zone_id);
        $this->assertSame(1047, $report->zone_id);
    }

    #[Test]
    public function it_can_be_created_without_zone(): void
    {
        $report = $this->factory()->withoutZone()->create();

        $this->assertTableHas([
            'code' => $report->code,
            'zone_id' => null,
            'zone_name' => null,
        ]);
    }

    #[Test]
    public function zone_accessor_returns_zone_object(): void
    {
        $report = $this->factory()->withZone(1047, 'Karazhan')->create();

        $report->refresh();

        $this->assertInstanceOf(Zone::class, $report->zone);
        $this->assertSame(1047, $report->zone->id);
        $this->assertSame('Karazhan', $report->zone->name);
    }

    #[Test]
    public function zone_accessor_returns_null_when_no_zone(): void
    {
        $report = $this->factory()->withoutZone()->create();

        $report->refresh();

        $this->assertNull($report->zone);
    }

    #[Test]
    public function characters_returns_belongs_to_many_relationship(): void
    {
        $report = new Report;

        $this->assertInstanceOf(BelongsToMany::class, $report->characters());
    }

    #[Test]
    public function it_can_attach_characters(): void
    {
        $report = $this->create();
        $character = Character::factory()->create();

        $report->characters()->attach($character->id);

        $this->assertCount(1, $report->characters);
        $this->assertSame($character->id, $report->characters->first()->id);
    }

    #[Test]
    public function it_can_attach_multiple_characters(): void
    {
        $report = $this->create();
        $characters = Character::factory()->count(3)->create();

        $report->characters()->attach($characters->pluck('id'));

        $this->assertCount(3, $report->characters);
    }

    #[Test]
    public function characters_returns_empty_collection_when_none_attached(): void
    {
        $report = $this->create();

        $this->assertCount(0, $report->characters);
    }

    #[Test]
    public function presence_defaults_to_zero(): void
    {
        $report = $this->create();
        $character = Character::factory()->create();

        $report->characters()->attach($character->id);

        $this->assertSame(0, $report->characters->first()->pivot->presence);
    }

    #[Test]
    public function presence_can_be_set_when_attaching(): void
    {
        $report = $this->create();
        $character = Character::factory()->create();

        $report->characters()->attach($character->id, ['presence' => 1]);

        $this->assertSame(1, $report->characters->first()->pivot->presence);
    }

    #[Test]
    public function presence_can_be_set_to_benched(): void
    {
        $report = $this->create();
        $character = Character::factory()->create();

        $report->characters()->attach($character->id, ['presence' => 2]);

        $this->assertSame(2, $report->characters->first()->pivot->presence);
    }

    #[Test]
    public function deleting_report_cascades_to_pivot(): void
    {
        $report = $this->create();
        $character = Character::factory()->create();

        $report->characters()->attach($character->id);

        $this->assertDatabaseHas('pivot_characters_wcl_reports', [
            'wcl_report_code' => $report->code,
            'character_id' => $character->id,
        ]);

        $report->delete();

        $this->assertDatabaseMissing('pivot_characters_wcl_reports', [
            'wcl_report_code' => $report->code,
        ]);
    }

    #[Test]
    public function deleting_character_cascades_to_pivot(): void
    {
        Event::fake([AddonSettingsProcessed::class]);

        $report = $this->create();
        $character = Character::factory()->create();

        $report->characters()->attach($character->id);

        $this->assertDatabaseHas('pivot_characters_wcl_reports', [
            'wcl_report_code' => $report->code,
            'character_id' => $character->id,
        ]);

        $character->delete();

        $this->assertDatabaseMissing('pivot_characters_wcl_reports', [
            'character_id' => $character->id,
        ]);
    }
}
