<?php

namespace Tests\Unit\Services\RaidHelper\Resources;

use App\Services\RaidHelper\Resources\EventSpec;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventSpecTest extends TestCase
{
    private function payload(): array
    {
        return [
            'name' => 'Restoration',
            'emoteId' => '1234567890',
            'roleName' => 'Healer',
            'roleEmoteId' => '9876543210',
        ];
    }

    #[Test]
    public function it_constructs_from_a_full_payload(): void
    {
        $spec = EventSpec::from($this->payload());

        $this->assertSame('Restoration', $spec->name);
        $this->assertSame('1234567890', $spec->emoteId);
        $this->assertSame('Healer', $spec->roleName);
        $this->assertSame('9876543210', $spec->roleEmoteId);
    }

    #[Test]
    public function to_array_produces_snake_case_keys(): void
    {
        $array = EventSpec::from($this->payload())->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('emote_id', $array);
        $this->assertArrayHasKey('role_name', $array);
        $this->assertArrayHasKey('role_emote_id', $array);
    }
}
