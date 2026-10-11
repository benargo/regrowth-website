<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs\Data\World;

use App\Http\Integrations\WarcraftLogs\Data\World\DifficultyData;
use App\Http\Integrations\WarcraftLogs\Data\World\ExpansionData;
use App\Http\Integrations\WarcraftLogs\Data\World\ZoneData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('warcraftlogs-integration')]
class ZoneDataTest extends TestCase
{
    #[Test]
    public function it_hydrates_scalar_fields_difficulties_and_expansion(): void
    {
        $zone = ZoneData::from($this->sampleApiResponse());

        $this->assertSame(1047, $zone->id);
        $this->assertSame('Karazhan', $zone->name);
        $this->assertFalse($zone->frozen);
        $this->assertCount(2, $zone->difficulties);
        $this->assertContainsOnlyInstancesOf(DifficultyData::class, $zone->difficulties);
        $this->assertSame([10, 25], $zone->difficulties[0]->sizes);
        $this->assertInstanceOf(ExpansionData::class, $zone->expansion);
        $this->assertSame('The Burning Crusade', $zone->expansion->name);
    }

    #[Test]
    public function it_defaults_the_optional_fields(): void
    {
        $zone = ZoneData::from(['id' => 1047, 'name' => 'Karazhan']);

        $this->assertSame([], $zone->difficulties);
        $this->assertFalse($zone->frozen);
        $this->assertNull($zone->expansion);
    }

    #[Test]
    public function it_serialises_to_the_stored_array_shape(): void
    {
        $payload = $this->sampleApiResponse();

        $this->assertSame($payload, ZoneData::from($payload)->toArray());
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(): array
    {
        return [
            'id' => 1047,
            'name' => 'Karazhan',
            'difficulties' => [
                ['id' => 3, 'name' => 'Normal', 'sizes' => [10, 25]],
                ['id' => 4, 'name' => 'Heroic', 'sizes' => [10, 25]],
            ],
            'frozen' => false,
            'expansion' => ['id' => 3, 'name' => 'The Burning Crusade', 'zones' => []],
        ];
    }
}
