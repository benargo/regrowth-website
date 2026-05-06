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
            'entryTime' => 1746316800,
        ];
    }

    private function serverEventsPayload(): array
    {
        return [
            'name' => 'Jaina',
            'id' => 1,
            'userId' => '80351110224678912',
            'entryTime' => 1746316800,
            'className' => 'Mage',
            'specName' => 'Frost',
        ];
    }

    private function fullPayload(): array
    {
        return [
            ...$this->minimalPayload(),
            'className' => 'Mage',
            'classEmoteId' => '1111111111',
            'specName' => 'Frost',
            'specEmoteId' => '2222222222',
            'roleName' => 'DPS',
            'roleEmoteId' => '3333333333',
            'cClassName' => 'Mage',
            'cSpecName' => 'Frost',
            'cRoleName' => 'DPS',
        ];
    }

    #[Test]
    public function it_constructs_from_a_minimal_payload(): void
    {
        $signup = SignUp::from($this->minimalPayload());

        $this->assertSame('Jaina', $signup->name);
        $this->assertSame(1, $signup->id);
        $this->assertSame('80351110224678912', $signup->userId);
        $this->assertSame(1746316800, $signup->entryTime);
    }

    #[Test]
    public function it_constructs_from_a_server_events_payload(): void
    {
        $signup = SignUp::from($this->serverEventsPayload());

        $this->assertSame('Jaina', $signup->name);
        $this->assertSame(1, $signup->id);
        $this->assertSame('80351110224678912', $signup->userId);
        $this->assertSame(1746316800, $signup->entryTime);
        $this->assertSame('Mage', $signup->className);
        $this->assertSame('Frost', $signup->specName);
        $this->assertNull($signup->status);
        $this->assertNull($signup->position);
    }

    #[Test]
    public function nullable_fields_default_to_null_when_omitted(): void
    {
        $signup = SignUp::from($this->minimalPayload());

        $this->assertNull($signup->status);
        $this->assertNull($signup->position);
        $this->assertNull($signup->className);
        $this->assertNull($signup->classEmoteId);
        $this->assertNull($signup->specName);
        $this->assertNull($signup->specEmoteId);
        $this->assertNull($signup->roleName);
        $this->assertNull($signup->roleEmoteId);
        $this->assertNull($signup->cClassName);
        $this->assertNull($signup->cSpecName);
        $this->assertNull($signup->cRoleName);
    }

    #[Test]
    public function it_stores_all_fields_from_a_full_payload(): void
    {
        $signup = SignUp::from($this->fullPayload());

        $this->assertSame('Mage', $signup->className);
        $this->assertSame('1111111111', $signup->classEmoteId);
        $this->assertSame('Frost', $signup->specName);
        $this->assertSame('2222222222', $signup->specEmoteId);
        $this->assertSame('DPS', $signup->roleName);
        $this->assertSame('3333333333', $signup->roleEmoteId);
        $this->assertSame('Mage', $signup->cClassName);
        $this->assertSame('Frost', $signup->cSpecName);
        $this->assertSame('DPS', $signup->cRoleName);
    }

    #[Test]
    public function it_stores_status_when_provided(): void
    {
        $signup = SignUp::from([...$this->fullPayload(), 'status' => 'primary']);

        $this->assertSame('primary', $signup->status);
    }

    #[Test]
    public function it_stores_position_when_provided(): void
    {
        $signup = SignUp::from([...$this->fullPayload(), 'position' => 5]);

        $this->assertSame(5, $signup->position);
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
        $this->assertArrayHasKey('spec_emote_id', $array);
        $this->assertArrayHasKey('role_name', $array);
        $this->assertArrayHasKey('role_emote_id', $array);
        $this->assertArrayHasKey('c_class_name', $array);
        $this->assertArrayHasKey('c_spec_name', $array);
        $this->assertArrayHasKey('c_role_name', $array);
    }
}
