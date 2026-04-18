<?php

namespace Tests\Unit\Services\WarcraftLogs\ValueObjects;

use App\Services\WarcraftLogs\ValueObjects\Expansion;
use App\Services\WarcraftLogs\ValueObjects\Zone;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExpansionTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function sampleData(): array
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

    #[Test]
    public function from_array_parses_all_fields(): void
    {
        $expansion = Expansion::fromArray($this->sampleData());

        $this->assertInstanceOf(Arrayable::class, $expansion);
        $this->assertInstanceOf(JsonSerializable::class, $expansion);
        $this->assertSame(3, $expansion->id);
        $this->assertSame('The Burning Crusade', $expansion->name);
        $this->assertCount(2, $expansion->zones);
        $this->assertContainsOnlyInstancesOf(Zone::class, $expansion->zones);
        $this->assertSame(1047, $expansion->zones[0]->id);
        $this->assertSame('Karazhan', $expansion->zones[0]->name);
        $this->assertSame(1048, $expansion->zones[1]->id);
        $this->assertSame('Serpentshrine Cavern', $expansion->zones[1]->name);
    }

    #[Test]
    public function from_array_defaults_to_empty_zones(): void
    {
        $expansion = Expansion::fromArray(['id' => 3, 'name' => 'The Burning Crusade']);

        $this->assertSame([], $expansion->zones);
    }

    #[Test]
    public function to_array_round_trips(): void
    {
        $data = $this->sampleData();

        $expansion = Expansion::fromArray($data);

        $this->assertSame($data, $expansion->toArray());
    }

    #[Test]
    public function json_serialize_matches_to_array(): void
    {
        $expansion = Expansion::fromArray($this->sampleData());

        $this->assertSame($expansion->toArray(), $expansion->jsonSerialize());
    }
}
