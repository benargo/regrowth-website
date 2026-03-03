<?php

namespace Tests\Unit\Services\Blizzard\Data;

use App\Services\Blizzard\Data\PlayableRace;
use App\Services\Blizzard\PlayableRaceService;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayableRaceTest extends TestCase
{
    #[Test]
    public function from_id_returns_correct_data(): void
    {
        $this->mock(PlayableRaceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('find')
                ->once()
                ->with(2)
                ->andReturn(['id' => 2, 'name' => 'Orc']);
        });

        $result = PlayableRace::fromId(2);

        $this->assertSame(2, $result->id);
        $this->assertSame('Orc', $result->name);
    }

    #[Test]
    public function from_id_falls_back_to_unknown_race_name_when_name_missing(): void
    {
        $this->mock(PlayableRaceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('find')->andReturn([]);
        });

        $result = PlayableRace::fromId(99);

        $this->assertSame('Unknown Race', $result->name);
    }

    #[Test]
    public function unknown_returns_null_id_and_unknown_race_name(): void
    {
        $result = PlayableRace::unknown();

        $this->assertNull($result->id);
        $this->assertSame('Unknown Race', $result->name);
    }

    #[Test]
    public function to_array_returns_expected_keys(): void
    {
        $result = PlayableRace::unknown()->toArray();

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertCount(2, $result);
    }
}
