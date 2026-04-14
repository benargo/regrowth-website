<?php

namespace Tests\Unit\Http\Resources\WarcraftLogs;

use App\Http\Resources\UserResource;
use App\Http\Resources\WarcraftLogs\LinkedReportResource;
use App\Models\Raids\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LinkedReportResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $report = Report::factory()->withoutGuildTag()->withZone(1001, 'Karazhan')->create();

        $array = (new LinkedReportResource($report))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('code', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('start_time', $array);
        $this->assertArrayHasKey('end_time', $array);
        $this->assertArrayHasKey('zone', $array);
        $this->assertArrayHasKey('pivot', $array);
    }

    #[Test]
    public function it_returns_correct_scalar_fields(): void
    {
        $report = Report::factory()->withoutGuildTag()->withZone(1001, 'Karazhan')->create();

        $array = (new LinkedReportResource($report))->toArray(new Request);

        $this->assertSame($report->id, $array['id']);
        $this->assertSame($report->code, $array['code']);
        $this->assertSame($report->title, $array['title']);
        $this->assertEquals($report->start_time, $array['start_time']);
        $this->assertEquals($report->end_time, $array['end_time']);
    }

    #[Test]
    public function it_returns_zone_as_array(): void
    {
        $report = Report::factory()->withoutGuildTag()->withZone(1001, 'Karazhan')->create();

        $array = (new LinkedReportResource($report))->toArray(new Request);

        $this->assertSame(['id' => 1001, 'name' => 'Karazhan'], $array['zone']);
    }

    #[Test]
    public function it_omits_pivot_when_not_from_pivot_table(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();

        $array = (new LinkedReportResource($report))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['pivot']);
    }

    #[Test]
    public function it_includes_pivot_data_when_loaded_via_linked_reports(): void
    {
        $report1 = Report::factory()->withoutGuildTag()->create();
        $report2 = Report::factory()->withoutGuildTag()->create();
        $report1->linkedReports()->attach($report2, ['created_by' => null]);

        $loadedReport = $report1->load('linkedReports');
        $linkedReport = $loadedReport->linkedReports->first();

        $array = (new LinkedReportResource($linkedReport))->toArray(new Request);

        $this->assertNull($array['pivot']['created_by']);
        $this->assertArrayHasKey('created_at', $array['pivot']);
        $this->assertArrayHasKey('updated_at', $array['pivot']);
    }

    #[Test]
    public function it_returns_created_by_as_user_resource_when_user_exists(): void
    {
        $user = User::factory()->create();
        $report1 = Report::factory()->withoutGuildTag()->create();
        $report2 = Report::factory()->withoutGuildTag()->create();
        $report1->linkedReports()->attach($report2, ['created_by' => $user->id]);

        $loadedReport = $report1->load('linkedReports');
        $linkedReport = $loadedReport->linkedReports->first();

        $array = (new LinkedReportResource($linkedReport))->toArray(new Request);

        $this->assertInstanceOf(UserResource::class, $array['pivot']['created_by']);
        $this->assertSame($user->id, $array['pivot']['created_by']->resource->id);
    }

    #[Test]
    public function it_does_not_include_nested_linked_reports(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();

        $array = (new LinkedReportResource($report))->toArray(new Request);

        $this->assertArrayNotHasKey('linked_reports', $array);
    }

    #[Test]
    public function it_does_not_include_characters(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();

        $array = (new LinkedReportResource($report))->toArray(new Request);

        $this->assertArrayNotHasKey('characters', $array);
    }
}
