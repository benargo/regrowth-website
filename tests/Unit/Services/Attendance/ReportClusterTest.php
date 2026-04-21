<?php

namespace Tests\Unit\Services\Attendance;

use App\Models\Character;
use App\Models\GuildRank;
use App\Models\Raids\Report;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Zone;
use App\Services\Attendance\PlayerPresenceData;
use App\Services\Attendance\ReportClusterData;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use JsonSerializable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportClusterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Europe/Paris']);
    }

    protected function makeTag(): GuildTag
    {
        return GuildTag::factory()->countsAttendance()->withoutPhase()->create();
    }

    protected function makeReport(GuildTag $tag, Carbon $startTime, ?Zone $zone = null, string|false|null $code = false): Report
    {
        $attributes = [
            'start_time' => $startTime,
            'zone_id' => $zone?->id,
        ];

        if ($code !== false) {
            $attributes['code'] = $code;
        }

        return Report::factory()->withGuildTag($tag)->create($attributes);
    }

    protected function attachCharacter(Report $report, Character $character, int $presence): void
    {
        $report->characters()->attach($character->id, ['presence' => $presence]);
    }

    protected function loadReports(): Collection
    {
        return Report::with(['characters', 'zone'])->get();
    }

    #[Test]
    public function it_throws_when_constructed_with_an_empty_collection(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ReportClusterData(collect());
    }

    #[Test]
    public function it_implements_arrayable_and_json_serializable(): void
    {
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));

        $cluster = new ReportClusterData(collect([$report]));

        $this->assertInstanceOf(Arrayable::class, $cluster);
        $this->assertInstanceOf(JsonSerializable::class, $cluster);
    }

    #[Test]
    public function single_report_id_is_the_report_id(): void
    {
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));

        $cluster = new ReportClusterData(collect([$report]));

        $this->assertSame($report->id, $cluster->id());
        $this->assertFalse($cluster->isMerged());
    }

    #[Test]
    public function merged_id_joins_sorted_report_ids_with_plus(): void
    {
        $tag = $this->makeTag();
        $r1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $r2 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));

        $cluster = new ReportClusterData(collect([$r2, $r1]));

        $ids = [$r1->id, $r2->id];
        sort($ids);
        $this->assertSame(implode('+', $ids), $cluster->id());
        $this->assertTrue($cluster->isMerged());
    }

    #[Test]
    public function single_report_code_is_the_report_code(): void
    {
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'), code: 'ABCDEF');

        $cluster = new ReportClusterData(collect([$report]));

        $this->assertSame('ABCDEF', $cluster->code());
    }

    #[Test]
    public function null_code_returns_null(): void
    {
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'), code: null);

        $cluster = new ReportClusterData(collect([$report]));

        $this->assertNull($cluster->code());
    }

    #[Test]
    public function merged_code_joins_sorted_non_null_codes_with_plus(): void
    {
        $tag = $this->makeTag();
        $r1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'), code: 'ZZZ');
        $r2 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'), code: 'AAA');

        $cluster = new ReportClusterData(collect([$r1, $r2]));

        $this->assertSame('AAA+ZZZ', $cluster->code());
    }

    #[Test]
    public function merged_code_filters_nulls_out(): void
    {
        $tag = $this->makeTag();
        $wcl = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'), code: 'ABC');
        $manual = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'), code: null);

        $cluster = new ReportClusterData(collect([$wcl, $manual]));

        $this->assertSame('ABC', $cluster->code());
    }

    #[Test]
    public function merged_code_is_null_when_all_reports_are_manual(): void
    {
        $tag = $this->makeTag();
        $m1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'), code: null);
        $m2 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'), code: null);

        $cluster = new ReportClusterData(collect([$m1, $m2]));

        $this->assertNull($cluster->code());
    }

    #[Test]
    public function start_time_is_the_earliest_report_start_time(): void
    {
        $tag = $this->makeTag();
        $late = $this->makeReport($tag, Carbon::parse('2025-01-15 21:00', 'Europe/Paris'));
        $early = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $mid = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));

        $cluster = new ReportClusterData(collect([$late, $mid, $early]));

        $this->assertTrue($cluster->startTime()->equalTo($early->start_time));
    }

    #[Test]
    public function zone_name_is_the_zone_of_the_earliest_report(): void
    {
        $zoneA = Zone::factory()->create(['name' => 'Zone A']);
        $zoneB = Zone::factory()->create(['name' => 'Zone B']);
        $tag = $this->makeTag();

        $early = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'), $zoneA);
        $late = $this->makeReport($tag, Carbon::parse('2025-01-15 21:00', 'Europe/Paris'), $zoneB);

        $cluster = new ReportClusterData(Report::with('zone')->whereIn('id', [$late->id, $early->id])->get());

        $this->assertSame('Zone A', $cluster->zoneName());
    }

    #[Test]
    public function zone_name_is_null_when_earliest_report_has_no_zone(): void
    {
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));

        $cluster = new ReportClusterData(Report::with('zone')->whereKey($report->id)->get());

        $this->assertNull($cluster->zoneName());
    }

    #[Test]
    public function players_contains_one_entry_per_character_for_a_single_report(): void
    {
        $rank = GuildRank::factory()->create();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $this->attachCharacter($report, $thrall, 1);
        $this->attachCharacter($report, $jaina, 2);

        $cluster = new ReportClusterData($this->loadReports());
        $players = $cluster->players();

        $this->assertCount(2, $players);
        $this->assertInstanceOf(PlayerPresenceData::class, $players['Thrall']);
        $this->assertSame(1, $players['Thrall']->presence);
        $this->assertSame(2, $players['Jaina']->presence);
    }

    #[Test]
    public function players_picks_best_presence_when_same_character_appears_in_multiple_reports(): void
    {
        $rank = GuildRank::factory()->create();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $r1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $r2 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));

        $this->attachCharacter($r1, $thrall, 0); // absent
        $this->attachCharacter($r2, $thrall, 1); // present — wins

        $cluster = new ReportClusterData($this->loadReports());
        $players = $cluster->players();

        $this->assertCount(1, $players);
        $this->assertSame(1, $players['Thrall']->presence);
    }

    #[Test]
    public function players_prefers_present_over_late(): void
    {
        $rank = GuildRank::factory()->create();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $r1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $r2 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));

        $this->attachCharacter($r1, $thrall, 2); // late
        $this->attachCharacter($r2, $thrall, 1); // present — wins (priority 2 > 1)

        $cluster = new ReportClusterData($this->loadReports());

        $this->assertSame(1, $cluster->players()['Thrall']->presence);
    }

    #[Test]
    public function players_prefers_late_over_absent(): void
    {
        $rank = GuildRank::factory()->create();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $r1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $r2 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));

        $this->attachCharacter($r1, $thrall, 0); // absent
        $this->attachCharacter($r2, $thrall, 2); // late — wins (priority 1 > 0)

        $cluster = new ReportClusterData($this->loadReports());

        $this->assertSame(2, $cluster->players()['Thrall']->presence);
    }

    #[Test]
    public function to_array_emits_the_expected_shape(): void
    {
        $rank = GuildRank::factory()->create();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $zone = Zone::factory()->create(['name' => 'Karazhan']);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'), $zone, 'WCLCODE');
        $this->attachCharacter($report, $thrall, 1);

        $cluster = new ReportClusterData(Report::with(['characters', 'zone'])->get());
        $array = $cluster->toArray();

        $this->assertSame($report->id, $array['id']);
        $this->assertSame('WCLCODE', $array['code']);
        $this->assertSame($report->start_time->toISOString(), $array['startTime']);
        $this->assertSame('Karazhan', $array['zoneName']);
        $this->assertCount(1, $array['players']);
        $this->assertSame('Thrall', $array['players'][0]['name']);
        $this->assertSame(1, $array['players'][0]['presence']);
    }

    #[Test]
    public function json_serialize_matches_to_array(): void
    {
        $rank = GuildRank::factory()->create();
        Character::factory()->create(['rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));

        $cluster = new ReportClusterData(Report::with(['characters', 'zone'])->get());

        $this->assertSame($cluster->toArray(), $cluster->jsonSerialize());
    }
}
