<?php

namespace Tests\Unit\Services\RaidHelper\Resources;

use App\Services\RaidHelper\Resources\EventClass;
use App\Services\RaidHelper\Resources\EventSpec;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventClassTest extends TestCase
{
    private function specPayload(): array
    {
        return [
            'name' => 'Restoration',
            'emoteId' => '1234567890',
            'roleName' => 'Healer',
            'roleEmoteId' => '9876543210',
        ];
    }

    private function payload(): array
    {
        return [
            'name' => 'Druid',
            'limit' => '5',
            'emoteId' => '1111111111',
            'type' => 'primary',
            'specs' => [$this->specPayload()],
        ];
    }

    #[Test]
    public function it_constructs_from_a_full_payload(): void
    {
        $raidClass = EventClass::from($this->payload());

        $this->assertSame('Druid', $raidClass->name);
        $this->assertSame('5', $raidClass->limit);
        $this->assertSame('1111111111', $raidClass->emoteId);
        $this->assertSame('primary', $raidClass->type);
    }

    #[Test]
    public function it_hydrates_specs_as_spec_instances(): void
    {
        $raidClass = EventClass::from($this->payload());

        $this->assertCount(1, $raidClass->specs);
        $this->assertInstanceOf(EventSpec::class, $raidClass->specs[0]);
        $this->assertSame('Restoration', $raidClass->specs[0]->name);
    }

    #[Test]
    public function it_accepts_an_empty_specs_array(): void
    {
        $raidClass = EventClass::from([...$this->payload(), 'specs' => []]);

        $this->assertIsArray($raidClass->specs);
        $this->assertCount(0, $raidClass->specs);
    }

    #[Test]
    public function to_array_produces_snake_case_keys(): void
    {
        $array = EventClass::from($this->payload())->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('limit', $array);
        $this->assertArrayHasKey('emote_id', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('specs', $array);
    }
}
