<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Channels;

use App\Http\Integrations\Discord\Contracts\Channel as ChannelContract;
use App\Http\Integrations\Discord\Data\Channels\ChannelStub;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

#[Group('discord-integration')]
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
    public function channel_stub_key_is_its_id(): void
    {
        $stub = new ChannelStub(
            id: '290926798999357250',
        );

        $this->assertSame('290926798999357250', $stub->getKey());
    }

    #[Test]
    public function channel_stub_can_be_notified(): void
    {
        Notification::fake();

        $stub = new ChannelStub(
            id: '290926798999357250',
        );
        $notification = new class extends BaseNotification
        {
            public function via(object $notifiable): array
            {
                return ['mail'];
            }
        };

        $stub->notify($notification);

        Notification::assertSentTo(new ChannelStub('290926798999357250'), $notification::class);
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
