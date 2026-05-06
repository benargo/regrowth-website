<?php

namespace Tests\Unit\Services\RaidHelper\Resources;

use App\Services\RaidHelper\Resources\CompSlot;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompSlotTest extends TestCase
{
    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'id' => '234728435400835073',
            'name' => 'Shiniko',
            'groupNumber' => 1,
            'slotNumber' => 1,
            'className' => 'Tank',
            'classEmoteId' => '580801859221192714',
            'specName' => 'Protection',
            'specEmoteId' => '637564444834136065',
            'isConfirmed' => 'confirmed',
            'color' => '#C69B6D',
        ];
    }

    #[Test]
    public function it_constructs_from_a_full_payload(): void
    {
        $slot = CompSlot::from($this->payload());

        $this->assertSame('234728435400835073', $slot->id);
        $this->assertSame('Shiniko', $slot->name);
        $this->assertSame(1, $slot->groupNumber);
        $this->assertSame(1, $slot->slotNumber);
        $this->assertSame('Tank', $slot->className);
        $this->assertSame('580801859221192714', $slot->classEmoteId);
        $this->assertSame('Protection', $slot->specName);
        $this->assertSame('637564444834136065', $slot->specEmoteId);
        $this->assertTrue($slot->isConfirmed);
        $this->assertSame('#C69B6D', $slot->color);
    }

    #[Test]
    public function it_casts_group_number_string_to_integer(): void
    {
        $slot = CompSlot::from([...$this->payload(), 'groupNumber' => '2']);

        $this->assertSame(2, $slot->groupNumber);
    }

    #[Test]
    public function it_casts_slot_number_string_to_integer(): void
    {
        $slot = CompSlot::from([...$this->payload(), 'slotNumber' => '3']);

        $this->assertSame(3, $slot->slotNumber);
    }

    #[Test]
    public function it_casts_confirmed_string_to_true(): void
    {
        $slot = CompSlot::from([...$this->payload(), 'isConfirmed' => 'confirmed']);

        $this->assertTrue($slot->isConfirmed);
    }

    #[Test]
    public function it_casts_unconfirmed_string_to_false(): void
    {
        $slot = CompSlot::from([...$this->payload(), 'isConfirmed' => 'unconfirmed']);

        $this->assertFalse($slot->isConfirmed);
    }

    #[Test]
    public function to_array_produces_snake_case_keys(): void
    {
        $slot = CompSlot::from($this->payload());

        $this->assertArrayHasKey('id', $slot->toArray());
        $this->assertArrayHasKey('name', $slot->toArray());
        $this->assertArrayHasKey('group_number', $slot->toArray());
        $this->assertArrayHasKey('slot_number', $slot->toArray());
        $this->assertArrayHasKey('class_name', $slot->toArray());
        $this->assertArrayHasKey('class_emote_id', $slot->toArray());
        $this->assertArrayHasKey('spec_name', $slot->toArray());
        $this->assertArrayHasKey('spec_emote_id', $slot->toArray());
        $this->assertArrayHasKey('is_confirmed', $slot->toArray());
        $this->assertArrayHasKey('color', $slot->toArray());
    }
}
