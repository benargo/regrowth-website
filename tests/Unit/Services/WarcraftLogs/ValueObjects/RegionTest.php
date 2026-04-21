<?php

namespace Tests\Unit\Services\WarcraftLogs\ValueObjects;

use App\Services\WarcraftLogs\ValueObjects\RegionData;
use Illuminate\Contracts\Support\Arrayable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegionTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function sampleData(): array
    {
        return [
            'id' => 1,
            'name' => 'EU',
            'slug' => 'eu',
        ];
    }

    #[Test]
    public function from_array_parses_all_fields(): void
    {
        $region = RegionData::from($this->sampleData());

        $this->assertInstanceOf(Arrayable::class, $region);
        $this->assertSame(1, $region->id);
        $this->assertSame('EU', $region->name);
        $this->assertSame('eu', $region->slug);
    }

    #[Test]
    public function to_array_round_trips(): void
    {
        $data = $this->sampleData();

        $region = RegionData::from($data);

        $this->assertSame($data, $region->toArray());
    }
}
