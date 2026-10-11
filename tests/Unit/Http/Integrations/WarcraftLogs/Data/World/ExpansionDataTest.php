<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs\Data\World;

use App\Http\Integrations\WarcraftLogs\Data\World\ExpansionData;
use App\Http\Integrations\WarcraftLogs\Data\World\ZoneData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('warcraftlogs-integration')]
class ExpansionDataTest extends TestCase
{
    #[Test]
    public function it_hydrates_id_name_and_nested_zones(): void
    {
        $expansion = ExpansionData::from($this->sampleApiResponse());

        $this->assertSame(3, $expansion->id);
        $this->assertSame('The Burning Crusade', $expansion->name);
        $this->assertCount(2, $expansion->zones);
        $this->assertContainsOnlyInstancesOf(ZoneData::class, $expansion->zones);
        $this->assertSame(1047, $expansion->zones[0]->id);
        $this->assertSame('Serpentshrine Cavern', $expansion->zones[1]->name);
    }

    #[Test]
    public function it_defaults_zones_to_an_empty_list(): void
    {
        $expansion = ExpansionData::from(['id' => 3, 'name' => 'The Burning Crusade']);

        $this->assertSame([], $expansion->zones);
    }

    #[Test]
    public function it_serialises_to_the_stored_array_shape(): void
    {
        $payload = $this->sampleApiResponse();

        $this->assertSame($payload, ExpansionData::from($payload)->toArray());
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(): array
    {
        return [
            'id' => 3,
            'name' => 'The Burning Crusade',
            'zones' => [
                ['id' => 1047, 'name' => 'Karazhan', 'difficulties' => [], 'frozen' => false, 'expansion' => null],
                ['id' => 1048, 'name' => 'Serpentshrine Cavern', 'difficulties' => [], 'frozen' => false, 'expansion' => null],
            ],
        ];
    }
}
