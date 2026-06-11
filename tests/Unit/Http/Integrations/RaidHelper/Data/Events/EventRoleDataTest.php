<?php

namespace Tests\Unit\Http\Integrations\RaidHelper\Data\Events;

use App\Http\Integrations\RaidHelper\Data\Events\EventRoleData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventRoleDataTest extends TestCase
{
    #[Test]
    public function it_constructs_from_a_full_payload(): void
    {
        $role = EventRoleData::from($this->payload());

        $this->assertSame('Healer', $role->name);
        $this->assertSame(10, $role->limit);
        $this->assertSame('1234567890', $role->emoteId);
    }

    #[Test]
    public function it_casts_limit_string_to_integer(): void
    {
        $role = EventRoleData::from([...$this->payload(), 'limit' => '999']);

        $this->assertSame(999, $role->limit);
    }

    #[Test]
    public function it_casts_limit_integer_directly(): void
    {
        $role = EventRoleData::from([...$this->payload(), 'limit' => 999]);

        $this->assertSame(999, $role->limit);
    }

    #[Test]
    public function optional_fields_default_to_null_when_omitted(): void
    {
        $role = EventRoleData::from($this->payload());

        $this->assertNull($role->cName);
    }

    #[Test]
    public function it_stores_c_name_when_provided(): void
    {
        $role = EventRoleData::from([...$this->payload(), 'cName' => 'Healers']);

        $this->assertSame('Healers', $role->cName);
    }

    #[Test]
    public function to_array_produces_snake_case_keys(): void
    {
        $array = EventRoleData::from([...$this->payload(), 'cName' => 'Healers'])->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('limit', $array);
        $this->assertArrayHasKey('emote_id', $array);
        $this->assertArrayHasKey('c_name', $array);
    }

    private function payload(): array
    {
        return [
            'name' => 'Healer',
            'limit' => '10',
            'emoteId' => '1234567890',
        ];
    }
}
