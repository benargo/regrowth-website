<?php

namespace Tests\Unit\Services\RaidHelper\Resources;

use App\Services\RaidHelper\Resources\EventRole;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventRoleTest extends TestCase
{
    private function payload(): array
    {
        return [
            'name' => 'Healer',
            'limit' => '10',
            'emoteId' => '1234567890',
        ];
    }

    #[Test]
    public function it_constructs_from_a_full_payload(): void
    {
        $role = EventRole::from($this->payload());

        $this->assertSame('Healer', $role->name);
        $this->assertSame('10', $role->limit);
        $this->assertSame('1234567890', $role->emoteId);
    }

    #[Test]
    public function to_array_produces_snake_case_keys(): void
    {
        $array = EventRole::from($this->payload())->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('limit', $array);
        $this->assertArrayHasKey('emote_id', $array);
    }
}
