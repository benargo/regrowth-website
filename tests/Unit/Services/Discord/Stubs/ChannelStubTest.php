<?php

namespace Tests\Unit\Services\Discord\Stubs;

use App\Services\Discord\Contracts\Resources\Channel as ChannelContract;
use App\Services\Discord\Stubs\ChannelStub;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

class ChannelStubTest extends TestCase
{
    #[Test]
    public function channel_stub_constructs_with_id(): void
    {
        $stub = new ChannelStub(
            id: '290926798999357250',
        );

        $this->assertSame('290926798999357250', $stub->id);
    }

    #[Test]
    public function channel_stub_implements_channel_contract(): void
    {
        $stub = new ChannelStub(
            id: '290926798999357250',
        );

        $this->assertInstanceOf(ChannelContract::class, $stub);
    }

    #[Test]
    public function channel_stub_properties_are_readonly(): void
    {
        $stub = new ChannelStub(
            id: '290926798999357250',
        );

        $reflection = new ReflectionClass($stub);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} on ChannelStub should be readonly."
            );
        }
    }
}
