<?php

namespace Tests\Unit\Services\WarcraftLogs\ValueObjects;

use App\Services\WarcraftLogs\ValueObjects\Zone;
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
        ];
    }

    #[Test]
    public function from_array_parses_all_fields(): void
    {
        $zone = Zone::fromArray($this->sampleData());

        $this->assertInstanceOf(Arrayable::class, $zone);
        $this->assertSame(1047, $zone->id);
        $this->assertSame('Karazhan', $zone->name);
    }

    #[Test]
    public function to_array_round_trips(): void
    {
        $data = $this->sampleData();

        $zone = Zone::fromArray($data);

        $this->assertSame($data, $zone->toArray());
    }
}
