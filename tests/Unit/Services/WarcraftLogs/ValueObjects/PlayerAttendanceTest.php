<?php

namespace Tests\Unit\Services\WarcraftLogs\ValueObjects;

use App\Services\WarcraftLogs\ValueObjects\PlayerAttendance;
use Illuminate\Contracts\Support\Arrayable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerAttendanceTest extends TestCase
{
    #[Test]
    public function it_creates_from_array(): void
    {
        $data = ['name' => 'Thrall', 'type' => 'Shaman', 'presence' => 1];

        $attendance = PlayerAttendance::fromArray($data);

        $this->assertInstanceOf(Arrayable::class, $attendance);
        $this->assertSame('Thrall', $attendance->name);
        $this->assertSame(1, $attendance->presence);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $attendance = new PlayerAttendance(name: 'Jaina', presence: 2);

        $result = $attendance->toArray();

        $this->assertSame([
            'name' => 'Jaina',
            'presence' => 2,
        ], $result);
    }

    #[Test]
    public function from_array_and_to_array_are_consistent(): void
    {
        $data = ['name' => 'Arthas', 'type' => 'Death Knight', 'presence' => 1];

        $attendance = PlayerAttendance::fromArray($data);
        $result = $attendance->toArray();

        $this->assertSame('Arthas', $result['name']);
        $this->assertSame(1, $result['presence']);
    }
}
