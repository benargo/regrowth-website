<?php

namespace Tests\Unit\Services\Blizzard\Data;

use App\Services\Blizzard\Data\PlayableRace;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayableRaceTest extends TestCase
{
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
