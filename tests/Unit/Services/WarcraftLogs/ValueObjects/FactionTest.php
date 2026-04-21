<?php

namespace Tests\Unit\Services\WarcraftLogs\ValueObjects;

use App\Services\WarcraftLogs\ValueObjects\FactionData;
use Illuminate\Contracts\Support\Arrayable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FactionTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function sampleData(): array
    {
        return [
            'id' => 1,
            'name' => 'Alliance',
        ];
    }

    #[Test]
    public function from_array_parses_all_fields(): void
    {
        $faction = FactionData::from($this->sampleData());

        $this->assertInstanceOf(Arrayable::class, $faction);
        $this->assertSame(1, $faction->id);
        $this->assertSame('Alliance', $faction->name);
    }

    #[Test]
    public function to_array_round_trips(): void
    {
        $data = $this->sampleData();

        $faction = FactionData::from($data);

        $this->assertSame($data, $faction->toArray());
    }
}
