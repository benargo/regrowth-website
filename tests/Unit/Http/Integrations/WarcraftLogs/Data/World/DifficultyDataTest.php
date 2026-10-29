<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs\Data\World;

use App\Http\Integrations\WarcraftLogs\Data\World\DifficultyData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('warcraftlogs-integration')]
class DifficultyDataTest extends TestCase
{
    #[Test]
    public function it_hydrates_id_name_and_sizes(): void
    {
        $difficulty = DifficultyData::from($this->sampleApiResponse());

        $this->assertSame(4, $difficulty->id);
        $this->assertSame('Heroic', $difficulty->name);
        $this->assertSame([10, 25], $difficulty->sizes);
    }

    #[Test]
    public function it_defaults_sizes_to_an_empty_list(): void
    {
        $difficulty = DifficultyData::from(['id' => 1, 'name' => 'Normal']);

        $this->assertSame([], $difficulty->sizes);
    }

    #[Test]
    public function it_serialises_to_the_stored_json_shape(): void
    {
        $difficulty = DifficultyData::from($this->sampleApiResponse());

        $this->assertSame('{"id":4,"name":"Heroic","sizes":[10,25]}', json_encode($difficulty));
    }

    /**
     * @return array{id: int, name: string, sizes: array<int, int>}
     */
    private function sampleApiResponse(): array
    {
        return ['id' => 4, 'name' => 'Heroic', 'sizes' => [10, 25]];
    }
}
