<?php

namespace Tests\Unit\Services\WarcraftLogs\ValueObjects;

use App\Services\WarcraftLogs\ValueObjects\DifficultyData;
use App\Services\WarcraftLogs\ValueObjects\ExpansionData;
use App\Services\WarcraftLogs\ValueObjects\ZoneData;
use Illuminate\Contracts\Support\Arrayable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ZoneTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function sampleData(): array
    {
        return [
            'id' => 1047,
            'name' => 'Karazhan',
            'difficulties' => [
                ['id' => 3, 'name' => 'Normal', 'sizes' => [10, 25]],
                ['id' => 4, 'name' => 'Heroic', 'sizes' => [10, 25]],
            ],
            'frozen' => false,
            'expansion' => [
                'id' => 3,
                'name' => 'The Burning Crusade',
                'zones' => [],
            ],
        ];
    }

    #[Test]
    public function from_array_parses_all_fields(): void
    {
        $zone = ZoneData::from($this->sampleData());

        $this->assertInstanceOf(Arrayable::class, $zone);
        $this->assertSame(1047, $zone->id);
        $this->assertSame('Karazhan', $zone->name);
        $this->assertCount(2, $zone->difficulties);
        $this->assertContainsOnlyInstancesOf(DifficultyData::class, $zone->difficulties);
        $this->assertSame(3, $zone->difficulties[0]->id);
        $this->assertSame('Normal', $zone->difficulties[0]->name);
        $this->assertSame([10, 25], $zone->difficulties[0]->sizes);
        $this->assertFalse($zone->frozen);
        $this->assertInstanceOf(ExpansionData::class, $zone->expansion);
        $this->assertSame(3, $zone->expansion->id);
        $this->assertSame('The Burning Crusade', $zone->expansion->name);
    }

    #[Test]
    public function from_array_defaults_optional_fields(): void
    {
        $zone = ZoneData::from(['id' => 1047, 'name' => 'Karazhan']);

        $this->assertSame([], $zone->difficulties);
        $this->assertFalse($zone->frozen);
        $this->assertNull($zone->expansion);
    }

    #[Test]
    public function to_array_round_trips(): void
    {
        $data = $this->sampleData();

        $zone = ZoneData::from($data);

        $this->assertSame($data, $zone->toArray());
    }
}
