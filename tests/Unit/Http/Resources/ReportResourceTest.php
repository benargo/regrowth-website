<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\ReportResource;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\GuildTag;
use App\Models\Raids\Report;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $report = Report::factory()->withoutGuildTag()->withZone(Zone::factory()->create(['id' => 1001, 'name' => 'Karazhan']))->create();

        $array = (new ReportResource($report))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('code', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('start_time', $array);
        $this->assertArrayHasKey('end_time', $array);
        $this->assertArrayHasKey('duration', $array);
        $this->assertArrayHasKey('guild_tag', $array);
        $this->assertArrayHasKey('zone', $array);
        $this->assertArrayHasKey('characters', $array);
        $this->assertArrayHasKey('linked_reports', $array);
    }

    #[Test]
    public function it_includes_linked_reports_count_when_counted(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $report2 = Report::factory()->withoutGuildTag()->create();
        $report->linkedReports()->attach($report2, ['created_by' => null]);

        $report->loadCount('linkedReports');

        $array = (new ReportResource($report))->toArray(new Request);

        $this->assertArrayHasKey('linked_reports_count', $array);
        $this->assertIsInt($array['linked_reports_count']);
        $this->assertSame(1, $array['linked_reports_count']);
    }

    #[Test]
    public function it_returns_correct_scalar_fields(): void
    {
        $report = Report::factory()->withoutGuildTag()->withZone(Zone::factory()->create(['id' => 1001, 'name' => 'Karazhan']))->create();

        $array = (new ReportResource($report))->toArray(new Request);

        $this->assertSame($report->id, $array['id']);
        $this->assertSame($report->code, $array['code']);
        $this->assertSame($report->title, $array['title']);
        $this->assertSame($report->start_time->toIso8601String(), $array['start_time']);
        $this->assertSame($report->end_time->toIso8601String(), $array['end_time']);
        $this->assertSame($report->duration, $array['duration']);
    }

    #[Test]
    public function it_returns_zone_as_array(): void
    {
        $report = Report::factory()->withoutGuildTag()->withZone(Zone::factory()->create(['id' => 1001, 'name' => 'Karazhan']))->create();

        $array = (new ReportResource($report))->toArray(new Request);

        $this->assertSame(['id' => 1001, 'name' => 'Karazhan'], $array['zone']);
    }

    #[Test]
    public function it_returns_no_zone_sentinel_when_zone_id_is_null(): void
    {
        $report = Report::factory()->withoutGuildTag()->withoutZone()->create();

        $array = (new ReportResource($report))->toArray(new Request);

        $this->assertSame(['id' => 0, 'name' => 'No zone'], $array['zone']);
    }

    #[Test]
    public function it_omits_guild_tag_when_not_loaded(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();

        $array = (new ReportResource($report))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['guild_tag']);
    }

    #[Test]
    public function it_includes_guild_tag_when_loaded(): void
    {
        $guildTag = GuildTag::factory()->create();
        $report = Report::factory()->withGuildTag($guildTag)->create();
        $report->load('guildTag');

        $array = (new ReportResource($report))->toArray(new Request);

        $this->assertIsArray($array['guild_tag']);
        $this->assertSame($guildTag->id, $array['guild_tag']['id']);
        $this->assertSame($guildTag->name, $array['guild_tag']['name']);
    }

    #[Test]
    public function it_omits_characters_when_not_loaded(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();

        $array = (new ReportResource($report))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['characters']);
    }

    #[Test]
    public function it_includes_characters_when_loaded(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $character = Character::factory()->create();
        $report->characters()->attach($character, ['presence' => 1]);
        $report->load('characters');

        $array = (new ReportResource($report))->toArray(new Request);

        $this->assertIsArray($array['characters']);
        $this->assertCount(1, $array['characters']);
    }

    #[Test]
    public function it_returns_characters_sorted_by_name(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $charlie = Character::factory()->create(['name' => 'Charlie']);
        $alice = Character::factory()->create(['name' => 'Alice']);
        $bob = Character::factory()->create(['name' => 'Bob']);
        $report->characters()->attach([$charlie->id => ['presence' => 1], $alice->id => ['presence' => 1], $bob->id => ['presence' => 1]]);
        $report->load('characters');

        $array = (new ReportResource($report))->toArray(new Request);

        $names = array_column($array['characters'], 'name');
        $this->assertSame(['Alice', 'Bob', 'Charlie'], $names);
    }

    #[Test]
    public function it_returns_character_pivot_data(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $character = Character::factory()->create();
        $report->characters()->attach($character, ['presence' => 1, 'is_loot_councillor' => true]);
        $report->load('characters');

        $array = (new ReportResource($report))->toArray(new Request);

        $pivot = $array['characters'][0]['pivot'];
        $this->assertSame(1, $pivot['presence']);
        $this->assertTrue($pivot['is_loot_councillor']);
    }

    #[Test]
    public function it_returns_character_rank_when_loaded(): void
    {
        $rank = GuildRank::factory()->create(['position' => 2, 'name' => 'Officer']);
        $report = Report::factory()->withoutGuildTag()->create();
        $character = Character::factory()->create(['rank_id' => $rank->id]);
        $report->characters()->attach($character, ['presence' => 1]);
        $report->load('characters.rank');

        $array = (new ReportResource($report))->toArray(new Request);

        $rankData = $array['characters'][0]['rank'];
        $this->assertIsArray($rankData);
        $this->assertSame($rank->id, $rankData['id']);
        $this->assertSame(2, $rankData['position']);
        $this->assertSame('Officer', $rankData['name']);
    }

    #[Test]
    public function it_omits_linked_reports_when_not_loaded(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();

        $array = (new ReportResource($report))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['linked_reports']);
    }

    #[Test]
    public function it_includes_linked_reports_when_loaded(): void
    {
        $report1 = Report::factory()->withoutGuildTag()->create();
        $report2 = Report::factory()->withoutGuildTag()->create();
        $report1->linkedReports()->attach($report2, ['created_by' => null]);
        $report1->load('linkedReports');

        $array = (new ReportResource($report1))->toArray(new Request);

        $this->assertIsArray($array['linked_reports']);
        $this->assertCount(1, $array['linked_reports']);

        $linked = $array['linked_reports'][0];
        $this->assertArrayHasKey('id', $linked);
        $this->assertArrayHasKey('title', $linked);
        $this->assertArrayHasKey('start_time', $linked);
        $this->assertArrayHasKey('zone', $linked);
        $this->assertArrayHasKey('pivot', $linked);
    }

    #[Test]
    public function it_returns_linked_report_pivot_with_creator(): void
    {
        $user = User::factory()->create(['nickname' => 'Raiderix', 'username' => 'raiderix#1234']);
        $report1 = Report::factory()->withoutGuildTag()->create();
        $report2 = Report::factory()->withoutGuildTag()->create();
        $report1->linkedReports()->attach($report2, ['created_by' => $user->id]);
        $report1->load('linkedReports');

        $array = (new ReportResource($report1))->toArray(new Request);

        $pivot = $array['linked_reports'][0]['pivot'];
        $this->assertNotNull($pivot['created_by']);
        $this->assertSame('Raiderix', $pivot['created_by']['display_name']);
    }
}
