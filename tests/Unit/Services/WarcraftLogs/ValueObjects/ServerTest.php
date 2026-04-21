<?php

namespace Tests\Unit\Services\WarcraftLogs\ValueObjects;

use App\Services\WarcraftLogs\ValueObjects\RegionData;
use App\Services\WarcraftLogs\ValueObjects\ServerData;
use Illuminate\Contracts\Support\Arrayable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServerTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function sampleData(): array
    {
        return [
            'id' => 1234,
            'name' => 'Pyrewood Village',
            'slug' => 'pyrewood-village',
            'region' => [
                'id' => 1,
                'name' => 'EU',
                'slug' => 'eu',
            ],
        ];
    }

    #[Test]
    public function from_array_parses_all_fields(): void
    {
        $server = ServerData::from($this->sampleData());

        $this->assertInstanceOf(Arrayable::class, $server);
        $this->assertSame(1234, $server->id);
        $this->assertSame('Pyrewood Village', $server->name);
        $this->assertSame('pyrewood-village', $server->slug);
        $this->assertInstanceOf(RegionData::class, $server->region);
        $this->assertSame(1, $server->region->id);
        $this->assertSame('EU', $server->region->name);
        $this->assertSame('eu', $server->region->slug);
    }

    #[Test]
    public function to_array_round_trips_including_nested_region(): void
    {
        $data = $this->sampleData();

        $server = ServerData::from($data);

        $this->assertSame($data, $server->toArray());
    }
}
