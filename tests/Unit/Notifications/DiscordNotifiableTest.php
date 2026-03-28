<?php

namespace Tests\Unit\Notifications;

use App\Notifications\DiscordNotifiable;
use Illuminate\Notifications\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscordNotifiableTest extends TestCase
{
    #[Test]
    public function it_creates_announcements_notifiable(): void
    {
        $notifiable = DiscordNotifiable::announcements();

        $this->assertSame('announcements', $notifiable->getKey());
    }

    #[Test]
    public function it_creates_officer_notifiable(): void
    {
        $notifiable = DiscordNotifiable::officer();

        $this->assertSame('officer', $notifiable->getKey());
    }

    #[Test]
    public function it_creates_channel_notifiable_with_custom_name(): void
    {
        $notifiable = DiscordNotifiable::channel('lootcouncil');

        $this->assertSame('lootcouncil', $notifiable->getKey());
    }

    #[Test]
    public function it_routes_numeric_channel_directly(): void
    {
        $notifiable = new DiscordNotifiable('1234567890');
        $notification = $this->createStub(Notification::class);

        $this->assertSame('1234567890', $notifiable->routeNotificationForDiscord($notification));
    }

    #[Test]
    public function it_routes_named_channel_from_config(): void
    {
        config(['services.discord.channels.officer' => '9876543210']);

        $notifiable = new DiscordNotifiable('officer');
        $notification = $this->createStub(Notification::class);

        $this->assertSame('9876543210', $notifiable->routeNotificationForDiscord($notification));
    }

    #[Test]
    public function it_returns_null_when_named_channel_not_in_config(): void
    {
        $notifiable = new DiscordNotifiable('nonexistent');
        $notification = $this->createStub(Notification::class);

        $this->assertNull($notifiable->routeNotificationForDiscord($notification));
    }
}
