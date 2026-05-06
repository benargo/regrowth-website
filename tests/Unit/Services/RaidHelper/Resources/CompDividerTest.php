<?php

namespace Tests\Unit\Services\RaidHelper\Resources;

use App\Services\RaidHelper\Resources\CompDivider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompDividerTest extends TestCase
{
    #[Test]
    public function it_constructs_from_a_payload(): void
    {
        $divider = CompDivider::from($this->payload());

        $this->assertSame('Edit Name', $divider->name);
        $this->assertSame(6, $divider->position);
    }

    #[Test]
    public function it_casts_position_string_to_integer(): void
    {
        $divider = CompDivider::from([...$this->payload(), 'position' => '6']);

        $this->assertSame(6, $divider->position);
        $this->assertIsInt($divider->position);
    }

    #[Test]
    public function to_array_produces_snake_case_keys(): void
    {
        $array = CompDivider::from($this->payload())->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('position', $array);
    }

    /** @return array{name: string, position: int} */
    private function payload(): array
    {
        return [
            'name' => 'Edit Name',
            'position' => 6,
        ];
    }
}
