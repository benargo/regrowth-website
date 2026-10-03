<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs\Data\Reports;

use App\Http\Integrations\WarcraftLogs\Data\GuildTags\GuildTagData;
use App\Http\Integrations\WarcraftLogs\Data\Reports\ReportData;
use App\Http\Integrations\WarcraftLogs\Data\World\DifficultyData;
use App\Http\Integrations\WarcraftLogs\Data\World\ExpansionData;
use App\Http\Integrations\WarcraftLogs\Data\World\ZoneData;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('warcraftlogs-integration')]
class ReportDataTest extends TestCase
{
    #[Test]
    public function it_hydrates_the_scalar_fields_and_millisecond_timestamps(): void
    {
        $report = ReportData::from($this->sampleApiResponse());

        $this->assertSame('aBcD1234', $report->code);
        $this->assertSame('Karazhan Clear', $report->title);
        $this->assertInstanceOf(Carbon::class, $report->startTime);
        $this->assertSame(1700000000123, $report->startTime->getTimestampMs());
        $this->assertSame(1700003600456, $report->endTime->getTimestampMs());
    }

    #[Test]
    public function it_hydrates_the_guild_tag_as_data_not_a_model(): void
    {
        $report = ReportData::from($this->sampleApiResponse());

        $this->assertInstanceOf(GuildTagData::class, $report->guildTag);
        $this->assertSame(1234, $report->guildTag->id);
        $this->assertSame('Main Raid', $report->guildTag->name);
    }

    #[Test]
    public function it_hydrates_the_nested_zone_difficulties_and_expansion(): void
    {
        $report = ReportData::from($this->sampleApiResponse());

        $this->assertInstanceOf(ZoneData::class, $report->zone);
        $this->assertSame(1047, $report->zone->id);
        $this->assertCount(1, $report->zone->difficulties);
        $this->assertContainsOnlyInstancesOf(DifficultyData::class, $report->zone->difficulties);
        $this->assertSame([10], $report->zone->difficulties[0]->sizes);
        $this->assertInstanceOf(ExpansionData::class, $report->zone->expansion);
        $this->assertSame(1001, $report->zone->expansion->id);
    }

    #[Test]
    public function it_hydrates_null_guild_tag_and_zone(): void
    {
        $report = ReportData::from([...$this->sampleApiResponse(), 'guildTag' => null, 'zone' => null]);

        $this->assertNull($report->guildTag);
        $this->assertNull($report->zone);
    }

    #[Test]
    public function it_hydrates_missing_guild_tag_and_zone_as_null(): void
    {
        $payload = $this->sampleApiResponse();
        unset($payload['guildTag'], $payload['zone']);

        $report = ReportData::from($payload);

        $this->assertNull($report->guildTag);
        $this->assertNull($report->zone);
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(): array
    {
        return [
            'code' => 'aBcD1234',
            'title' => 'Karazhan Clear',
            'startTime' => 1700000000123.0,
            'endTime' => 1700003600456.0,
            'guildTag' => ['id' => 1234, 'name' => 'Main Raid'],
            'zone' => [
                'id' => 1047,
                'name' => 'Karazhan',
                'difficulties' => [['id' => 3, 'name' => 'Normal', 'sizes' => [10]]],
                'expansion' => ['id' => 1001, 'name' => 'The Burning Crusade'],
            ],
        ];
    }
}
