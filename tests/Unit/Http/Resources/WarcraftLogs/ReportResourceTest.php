<?php

namespace Tests\Unit\Http\Resources\WarcraftLogs;

use App\Http\Resources\WarcraftLogs\ReportResource;
use App\Models\Character;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\MissingValue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $report = Report::factory()->withoutGuildTag()->withZone(1001, 'Karazhan')->create();

        $array = (new ReportResource($report))->toArray(new Request);

        $this->assertArrayHasKey('code', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('start_time', $array);
        $this->assertArrayHasKey('end_time', $array);
        $this->assertArrayHasKey('guild_tag', $array);
        $this->assertArrayHasKey('zone', $array);
        $this->assertArrayHasKey('characters', $array);
        $this->assertArrayHasKey('linked_reports', $array);
    }

    #[Test]
    public function it_returns_correct_scalar_fields(): void
    {
        $report = Report::factory()->withoutGuildTag()->withZone(1001, 'Karazhan')->create();

        $array = (new ReportResource($report))->toArray(new Request);

        $this->assertSame($report->code, $array['code']);
        $this->assertSame($report->title, $array['title']);
        $this->assertEquals($report->start_time, $array['start_time']);
        $this->assertEquals($report->end_time, $array['end_time']);
    }

    #[Test]
    public function it_returns_zone_as_array(): void
    {
        $report = Report::factory()->withoutGuildTag()->withZone(1001, 'Karazhan')->create();

        $array = (new ReportResource($report))->toArray(new Request);

        $this->assertSame(['id' => 1001, 'name' => 'Karazhan'], $array['zone']);
    }

    #[Test]
    public function it_returns_null_zone_values_when_no_zone(): void
    {
        $report = Report::factory()->withoutGuildTag()->withoutZone()->create();

        $array = (new ReportResource($report))->toArray(new Request);

        $this->assertSame(['id' => null, 'name' => null], $array['zone']);
    }

    #[Test]
    public function it_omits_guild_tag_when_not_loaded(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();

        $array = (new ReportResource($report))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['guild_tag']->resource);
    }

    #[Test]
    public function it_includes_guild_tag_when_loaded(): void
    {
        $guildTag = GuildTag::factory()->create();
        $report = Report::factory()->withGuildTag($guildTag)->create();
        $report->load('guildTag');

        $array = (new ReportResource($report))->toArray(new Request);

        $this->assertSame($guildTag->id, $array['guild_tag']->resource->id);
    }

    #[Test]
    public function it_omits_characters_when_not_loaded(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();

        $array = (new ReportResource($report))->toArray(new Request);

        $this->assertInstanceOf(AnonymousResourceCollection::class, $array['characters']);
        $this->assertInstanceOf(MissingValue::class, $array['characters']->resource);
    }

    #[Test]
    public function it_includes_characters_when_loaded(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $character = Character::factory()->create();
        $report->characters()->attach($character, ['presence' => 1]);
        $report->load('characters');

        $array = (new ReportResource($report))->toArray(new Request);

        $this->assertInstanceOf(AnonymousResourceCollection::class, $array['characters']);
        $this->assertCount(1, $array['characters']->collection);
    }

    #[Test]
    public function it_omits_linked_reports_when_not_loaded(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();

        $array = (new ReportResource($report))->toArray(new Request);

        $this->assertInstanceOf(AnonymousResourceCollection::class, $array['linked_reports']);
        $this->assertInstanceOf(MissingValue::class, $array['linked_reports']->resource);
    }

    #[Test]
    public function it_includes_linked_reports_when_loaded(): void
    {
        $report1 = Report::factory()->withoutGuildTag()->create();
        $report2 = Report::factory()->withoutGuildTag()->create();
        $report1->linkedReports()->attach($report2, ['created_by' => null]);
        $report1->load('linkedReports');

        $array = (new ReportResource($report1))->toArray(new Request);

        $this->assertInstanceOf(AnonymousResourceCollection::class, $array['linked_reports']);
        $this->assertCount(1, $array['linked_reports']->collection);
    }
}
