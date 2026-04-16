<?php

namespace Tests\Unit\Services\WarcraftLogs\ValueObjects;

use App\Models\WarcraftLogs\GuildTag;
use App\Services\WarcraftLogs\ValueObjects\Report;
use App\Services\WarcraftLogs\ValueObjects\Zone;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function from_array_parses_correctly_with_zone(): void
    {
        $data = [
            'code' => 'Tcdkf1AZQyFPRKBa',
            'title' => 'Karazhan Group 2',
            'startTime' => 1771612483423,
            'endTime' => 1771626471711,
            'zone' => [
                'id' => 1047,
                'name' => 'Karazhan',
            ],
        ];

        $report = Report::fromArray($data);

        $this->assertInstanceOf(Arrayable::class, $report);
        $this->assertSame('Tcdkf1AZQyFPRKBa', $report->code);
        $this->assertSame('Karazhan Group 2', $report->title);
        $this->assertInstanceOf(Carbon::class, $report->startTime);
        $this->assertEquals(1771612483423, $report->startTime->valueOf());
        $this->assertInstanceOf(Carbon::class, $report->endTime);
        $this->assertEquals(1771626471711, $report->endTime->valueOf());
        $this->assertInstanceOf(Zone::class, $report->zone);
        $this->assertSame(1047, $report->zone->id);
        $this->assertSame('Karazhan', $report->zone->name);
    }

    #[Test]
    public function from_array_parses_correctly_without_zone(): void
    {
        $data = [
            'code' => 'ABC123',
            'title' => 'Test Report',
            'startTime' => 1771611168498,
            'endTime' => 1771625431211,
        ];

        $report = Report::fromArray($data);

        $this->assertSame('ABC123', $report->code);
        $this->assertNull($report->zone);
    }

    #[Test]
    public function from_array_parses_guild_tag_correctly(): void
    {
        $guildTag = GuildTag::factory()->create(['name' => 'Main Roster']);

        $data = [
            'code' => 'ABC123',
            'title' => 'Test Report',
            'startTime' => 1771611168498,
            'endTime' => 1771625431211,
            'guildTag' => ['id' => $guildTag->id, 'name' => $guildTag->name],
        ];

        $report = Report::fromArray($data);

        $this->assertInstanceOf(GuildTag::class, $report->guildTag);
        $this->assertSame($guildTag->id, $report->guildTag->id);
        $this->assertSame('Main Roster', $report->guildTag->name);
    }

    #[Test]
    public function guild_tag_is_null_when_absent(): void
    {
        $data = [
            'code' => 'ABC123',
            'title' => 'Test Report',
            'startTime' => 1771611168498,
            'endTime' => 1771625431211,
        ];

        $report = Report::fromArray($data);

        $this->assertNull($report->guildTag);
    }

    #[Test]
    public function to_array_includes_zone_and_timestamps(): void
    {
        $data = [
            'code' => 'ABC123',
            'title' => 'Test Report',
            'startTime' => 1771611168498,
            'endTime' => 1771625431211,
            'zone' => ['id' => 1047, 'name' => 'Karazhan'],
        ];

        $report = Report::fromArray($data);
        $array = $report->toArray();

        $this->assertSame('ABC123', $array['code']);
        $this->assertSame('Test Report', $array['title']);
        $this->assertSame(1771611168498.0, $array['startTime']);
        $this->assertSame(1771625431211.0, $array['endTime']);
        $this->assertSame(['id' => 1047, 'name' => 'Karazhan'], $array['zone']);
    }

    #[Test]
    public function to_array_omits_zone_when_absent(): void
    {
        $data = [
            'code' => 'ABC123',
            'title' => 'Test Report',
            'startTime' => 1771611168498,
            'endTime' => 1771625431211,
        ];

        $report = Report::fromArray($data);
        $array = $report->toArray();

        $this->assertArrayNotHasKey('zone', $array);
    }
}
