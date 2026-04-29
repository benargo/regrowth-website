<?php

namespace Tests\Unit\Services\Discord\Stubs;

use App\Services\Discord\Contracts\Resources\Message as MessageContract;
use App\Services\Discord\Stubs\MessageStub;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

class MessageStubTest extends TestCase
{
    #[Test]
    public function message_stub_constructs_with_id_and_channel_id(): void
    {
        $stub = new MessageStub(
            id: '334385199974967042',
            channel_id: '290926798999357250',
        );

        $this->assertSame('334385199974967042', $stub->id);
        $this->assertSame('290926798999357250', $stub->channel_id);
    }

    #[Test]
    public function message_stub_implements_message_contract(): void
    {
        $stub = new MessageStub(
            id: '334385199974967042',
            channel_id: '290926798999357250',
        );

        $this->assertInstanceOf(MessageContract::class, $stub);
    }

    #[Test]
    public function message_stub_properties_are_readonly(): void
    {
        $stub = new MessageStub(
            id: '334385199974967042',
            channel_id: '290926798999357250',
        );

        $reflection = new ReflectionClass($stub);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} on MessageStub should be readonly."
            );
        }
    }
}
