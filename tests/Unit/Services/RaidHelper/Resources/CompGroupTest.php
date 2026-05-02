<?php

namespace Tests\Unit\Services\RaidHelper\Resources;

use App\Services\RaidHelper\Resources\CompGroup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompGroupTest extends TestCase
{
    #[Test]
    public function it_constructs_from_a_payload(): void
    {
        $group = CompGroup::from($this->payload());

        $this->assertSame('Group 1', $group->name);
        $this->assertSame(1, $group->position);
    }

    #[Test]
    public function it_casts_position_string_to_integer(): void
    {
        $group = CompGroup::from([...$this->payload(), 'position' => '1']);

        $this->assertSame(1, $group->position);
        $this->assertIsInt($group->position);
    }

    #[Test]
    public function to_array_produces_snake_case_keys(): void
    {
        $array = CompGroup::from($this->payload())->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('position', $array);
    }

    /** @return array{name: string, position: int} */
    private function payload(): array
    {
        return [
            'name' => 'Group 1',
            'position' => 1,
        ];
    }
}
