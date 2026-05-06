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
    public function optional_fields_default_to_null_or_default_when_omitted(): void
    {
        $spec = EventSpec::from($this->payload());

        $this->assertNull($spec->cName);
        $this->assertSame(999, $spec->limit);
    }

    #[Test]
    public function it_stores_c_name_when_provided(): void
    {
        $spec = EventSpec::from(array_merge($this->payload(), ['cName' => 'Restoration']));

        $this->assertSame('Restoration', $spec->cName);
    }

    #[Test]
    public function it_stores_limit_when_provided(): void
    {
        $spec = EventSpec::from(array_merge($this->payload(), ['limit' => 5]));

        $this->assertSame(5, $spec->limit);
    }

    #[Test]
    public function it_casts_limit_string_to_integer(): void
    {
        $spec = EventSpec::from(array_merge($this->payload(), ['limit' => '10']));

        $this->assertSame(10, $spec->limit);
    }

    #[Test]
    public function to_array_produces_snake_case_keys(): void
    {
        $array = EventSpec::from(array_merge($this->payload(), ['cName' => 'Restoration', 'limit' => 5]))->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('emote_id', $array);
        $this->assertArrayHasKey('role_name', $array);
        $this->assertArrayHasKey('role_emote_id', $array);
        $this->assertArrayHasKey('c_name', $array);
        $this->assertArrayHasKey('limit', $array);
    }
}
