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
        $this->assertSame(5, $raidClass->limit);
        $this->assertSame('1111111111', $raidClass->emoteId);
        $this->assertSame('primary', $raidClass->type);
    }

    #[Test]
    public function it_casts_limit_string_to_integer(): void
    {
        $raidClass = EventClass::from([...$this->payload(), 'limit' => '10']);

        $this->assertSame(10, $raidClass->limit);
    }

    #[Test]
    public function it_casts_limit_integer_directly(): void
    {
        $raidClass = EventClass::from([...$this->payload(), 'limit' => 999]);

        $this->assertSame(999, $raidClass->limit);
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
    public function optional_fields_default_to_null_when_omitted(): void
    {
        $raidClass = EventClass::from($this->payload());

        $this->assertNull($raidClass->cName);
        $this->assertNull($raidClass->effectiveName);
    }

    #[Test]
    public function it_stores_c_name_when_provided(): void
    {
        $raidClass = EventClass::from([...$this->payload(), 'cName' => 'Druid']);

        $this->assertSame('Druid', $raidClass->cName);
    }

    #[Test]
    public function it_stores_effective_name_when_provided(): void
    {
        $raidClass = EventClass::from([...$this->payload(), 'effectiveName' => 'Absence']);

        $this->assertSame('Absence', $raidClass->effectiveName);
    }

    #[Test]
    public function to_array_produces_snake_case_keys(): void
    {
        $array = EventClass::from([...$this->payload(), 'cName' => 'Druid', 'effectiveName' => 'Absence'])->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('limit', $array);
        $this->assertArrayHasKey('emote_id', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('specs', $array);
        $this->assertArrayHasKey('c_name', $array);
        $this->assertArrayHasKey('effective_name', $array);
    }
}
