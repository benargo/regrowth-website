<?php

namespace Tests\Unit\Services\RaidHelper\Resources;

use App\Services\RaidHelper\Resources\SignUp;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SignUpTest extends TestCase
{
    private function minimalPayload(): array
    {
        return [
            'name' => 'Jaina',
            'id' => 1,
            'userId' => '80351110224678912',
            'status' => 'primary',
            'entryTime' => 1746316800,
            'position' => 1,
        ];
    }

    private function fullPayload(): array
    {
        return [
            ...$this->minimalPayload(),
            'className' => 'Mage',
            'classEmoteId' => '1111111111',
            'specName' => 'Frost',
            'spec2Name' => 'Fire',
            'spec3Name' => 'Arcane',
            'specEmoteId' => '2222222222',
            'roleName' => 'DPS',
            'roleEmoteId' => '3333333333',
        ];
    }

    #[Test]
    public function it_constructs_from_a_minimal_payload(): void
    {
        $signup = SignUp::from($this->minimalPayload());

        $this->assertSame('Jaina', $signup->name);
        $this->assertSame(1, $signup->id);
        $this->assertSame('80351110224678912', $signup->userId);
        $this->assertSame('primary', $signup->status);
        $this->assertSame(1746316800, $signup->entryTime);
        $this->assertSame(1, $signup->position);
    }

    #[Test]
    public function nullable_fields_default_to_null_when_omitted(): void
    {
        $signup = SignUp::from($this->minimalPayload());

        $this->assertNull($signup->className);
        $this->assertNull($signup->classEmoteId);
        $this->assertNull($signup->specName);
        $this->assertNull($signup->spec2Name);
        $this->assertNull($signup->spec3Name);
        $this->assertNull($signup->specEmoteId);
        $this->assertNull($signup->roleName);
        $this->assertNull($signup->roleEmoteId);
    }

    #[Test]
    public function it_stores_all_fields_from_a_full_payload(): void
    {
        $signup = SignUp::from($this->fullPayload());

        $this->assertSame('Mage', $signup->className);
        $this->assertSame('1111111111', $signup->classEmoteId);
        $this->assertSame('Frost', $signup->specName);
        $this->assertSame('Fire', $signup->spec2Name);
        $this->assertSame('Arcane', $signup->spec3Name);
        $this->assertSame('2222222222', $signup->specEmoteId);
        $this->assertSame('DPS', $signup->roleName);
        $this->assertSame('3333333333', $signup->roleEmoteId);
    }

    #[Test]
    public function to_array_produces_snake_case_keys(): void
    {
        $array = SignUp::from($this->fullPayload())->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('user_id', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('entry_time', $array);
        $this->assertArrayHasKey('position', $array);
        $this->assertArrayHasKey('class_name', $array);
        $this->assertArrayHasKey('class_emote_id', $array);
        $this->assertArrayHasKey('spec_name', $array);
        $this->assertArrayHasKey('spec2_name', $array);
        $this->assertArrayHasKey('spec3_name', $array);
        $this->assertArrayHasKey('spec_emote_id', $array);
        $this->assertArrayHasKey('role_name', $array);
        $this->assertArrayHasKey('role_emote_id', $array);
    }
}
