<?php

namespace Tests\Unit\Models\WarcraftLogs;

use App\Events\AddonSettingsProcessed;
use App\Events\ReportCreated;
use App\Events\ReportUpdated;
use App\Models\Character;
use App\Models\Raids\Report;
use App\Models\User;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Zone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

        $this->assertSame('raid_reports', $report->getTable());
    }

    #[Test]
    public function it_has_string_primary_key(): void
    {
        $report = new Report;

        $this->assertSame('id', $report->getKeyName());
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
            'guild_tag_id',
            'zone_id',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $report = new Report;

        $this->assertCasts($report, [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
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
    public function it_can_be_created_without_zone(): void
    {
        $report = $this->factory()->withoutZone()->create();

        $this->assertTableHas([
            'code' => $report->code,
            'zone_id' => null,
        ]);
    }

    #[Test]
    public function zone_returns_belongs_to_relationship(): void
    {
        $report = new Report;

        $this->assertInstanceOf(BelongsTo::class, $report->zone());
    }

    #[Test]
    public function zone_relationship_returns_zone_model(): void
    {
        $zone = Zone::factory()->create();
        $report = $this->factory()->withZone($zone)->create();

        $report->refresh();

        $this->assertInstanceOf(Zone::class, $report->zone);
        $this->assertSame($zone->id, $report->zone->id);
        $this->assertSame($zone->name, $report->zone->name);
    }

    #[Test]
    public function zone_relationship_returns_null_when_no_zone(): void
    {
        $report = $this->factory()->withoutZone()->create();

        $report->refresh();

        $this->assertNull($report->zone);
    }

    #[Test]
    public function expansion_accessor_returns_expansion_from_zone(): void
    {
        $zone = Zone::factory()->create();
        $report = $this->factory()->withZone($zone)->create();

        $report->refresh();
        $report->load('zone');

        $this->assertEquals($zone->expansion, $report->expansion);
    }

    #[Test]
    public function expansion_accessor_returns_null_when_no_zone(): void
    {
        $report = $this->factory()->withoutZone()->create();

        $report->refresh();

        $this->assertNull($report->expansion);
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

        $this->assertDatabaseHas('pivot_characters_raid_reports', [
            'raid_report_id' => $report->id,
            'character_id' => $character->id,
        ]);

        $report->delete();

        $this->assertDatabaseMissing('pivot_characters_raid_reports', [
            'raid_report_id' => $report->id,
        ]);
    }

    #[Test]
    public function deleting_character_cascades_to_pivot(): void
    {
        Event::fake([AddonSettingsProcessed::class]);

        $report = $this->create();
        $character = Character::factory()->create();

        $report->characters()->attach($character->id);

        $this->assertDatabaseHas('pivot_characters_raid_reports', [
            'raid_report_id' => $report->id,
            'character_id' => $character->id,
        ]);

        $character->delete();

        $this->assertDatabaseMissing('pivot_characters_raid_reports', [
            'character_id' => $character->id,
        ]);
    }

    #[Test]
    public function it_belongs_to_a_guild_tag(): void
    {
        $guildTag = GuildTag::factory()->create();
        $report = $this->factory()->withGuildTag($guildTag)->create();

        $this->assertRelation($report, 'guildTag', BelongsTo::class);
        $this->assertSame($guildTag->id, $report->guildTag->id);
    }

    #[Test]
    public function guild_tag_relationship_returns_null_when_no_guild_tag_associated(): void
    {
        $report = $this->factory()->withoutGuildTag()->create();

        $this->assertNull($report->guildTag);
    }

    #[Test]
    public function factory_with_guild_tag_state_associates_a_guild_tag(): void
    {
        $report = $this->factory()->withGuildTag()->create();

        $this->assertNotNull($report->guild_tag_id);
        $this->assertNotNull($report->guildTag);
    }

    #[Test]
    public function factory_with_guild_tag_state_accepts_specific_guild_tag(): void
    {
        $guildTag = GuildTag::factory()->create(['name' => 'Main Roster']);

        $report = $this->factory()->withGuildTag($guildTag)->create();

        $this->assertSame($guildTag->id, $report->guild_tag_id);
        $this->assertSame('Main Roster', $report->guildTag->name);
    }

    #[Test]
    public function factory_without_guild_tag_state_sets_null_guild_tag(): void
    {
        $report = $this->factory()->withoutGuildTag()->create();

        $this->assertNull($report->guild_tag_id);
    }

    #[Test]
    public function deleting_guild_tag_sets_report_guild_tag_id_to_null(): void
    {
        $guildTag = GuildTag::factory()->create();
        $report = $this->factory()->withGuildTag($guildTag)->create();

        $this->assertSame($guildTag->id, $report->guild_tag_id);

        $guildTag->delete();

        $report->refresh();

        $this->assertNull($report->guild_tag_id);
    }

    // ==================== events ====================

    #[Test]
    public function it_dispatches_report_created_event_on_create(): void
    {
        Event::fake([ReportCreated::class]);

        $report = $this->create();

        Event::assertDispatched(ReportCreated::class, fn ($e) => $e->report->is($report));
    }

    #[Test]
    public function it_dispatches_report_updated_event_on_update(): void
    {
        $report = $this->create();

        Event::fake([ReportUpdated::class]);

        $report->update(['title' => 'Updated Title']);

        Event::assertDispatched(ReportUpdated::class, fn ($e) => $e->report->is($report));
    }

    // ==================== linkedReports ====================

    #[Test]
    public function linked_reports_returns_belongs_to_many_relationship(): void
    {
        $report = new Report;

        $this->assertInstanceOf(BelongsToMany::class, $report->linkedReports());
    }

    #[Test]
    public function linked_reports_returns_linked_reports(): void
    {
        $report1 = $this->create();
        $report2 = $this->create();

        \DB::table('raid_report_links')->insert([
            ['report_1' => $report1->id, 'report_2' => $report2->id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
            ['report_1' => $report2->id, 'report_2' => $report1->id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $linked = $report1->linkedReports;

        $this->assertCount(1, $linked);
        $this->assertSame($report2->code, $linked->first()->code);
    }

    #[Test]
    public function linked_reports_returns_manually_linked_reports_with_created_by(): void
    {
        $report1 = $this->create();
        $report2 = $this->create();
        $officer = User::factory()->officer()->create();

        \DB::table('raid_report_links')->insert([
            ['report_1' => $report1->id, 'report_2' => $report2->id, 'created_by' => $officer->id, 'created_at' => now(), 'updated_at' => now()],
            ['report_1' => $report2->id, 'report_2' => $report1->id, 'created_by' => $officer->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $linked = $report1->linkedReports;

        $this->assertCount(1, $linked);
        $this->assertSame($report2->code, $linked->first()->code);
        $this->assertSame($officer->id, $linked->first()->pivot->created_by);
    }

    #[Test]
    public function deleting_report_cascades_to_linked_reports_pivot(): void
    {
        $report1 = $this->create();
        $report2 = $this->create();

        \DB::table('raid_report_links')->insert([
            ['report_1' => $report1->id, 'report_2' => $report2->id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
            ['report_1' => $report2->id, 'report_2' => $report1->id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $report1->delete();

        $this->assertDatabaseMissing('raid_report_links', ['report_1' => $report1->id]);
        $this->assertDatabaseMissing('raid_report_links', ['report_2' => $report1->id]);
    }
}
