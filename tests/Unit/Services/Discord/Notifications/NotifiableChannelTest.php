<?php

namespace Tests\Unit\Services\Discord\Notifications;

use App\Services\Discord\Discord;
use App\Services\Discord\Enums\ChannelType;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Resources\Channel;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class NotifiableChannelTest extends TestCase
{
    private Discord&MockInterface $discord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discord = Mockery::mock(Discord::class);
    }

    // -------------------------------------------------------------------------
    // getKey
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_the_channel_id_as_the_key(): void
    {
        $channel = Channel::from(['id' => '111222333444555666', 'type' => ChannelType::GUILD_TEXT->value]);

        $notifiable = new NotifiableChannel($channel);

        $this->assertSame('111222333444555666', $notifiable->getKey());
    }

    // -------------------------------------------------------------------------
    // constructor / channel()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_the_channel_passed_to_the_constructor(): void
    {
        $channel = Channel::from(['id' => '111222333444555666', 'type' => ChannelType::GUILD_TEXT->value]);

        $notifiable = new NotifiableChannel($channel);

        $this->assertSame($channel, $notifiable->channel());
    }

    // -------------------------------------------------------------------------
    // fromChannelId
    // -------------------------------------------------------------------------

    #[Test]
    public function it_resolves_a_channel_from_a_channel_id(): void
    {
        $channel = Channel::from(['id' => '111222333444555666', 'type' => ChannelType::GUILD_TEXT->value]);

        $this->discord->expects('getChannel')
            ->with('111222333444555666')
            ->andReturn($channel);

        $notifiable = NotifiableChannel::fromChannelId('111222333444555666', $this->discord);

        $this->assertInstanceOf(NotifiableChannel::class, $notifiable);
        $this->assertSame($channel, $notifiable->channel());
        $this->assertSame('111222333444555666', $notifiable->channel()->id);
    }

    // -------------------------------------------------------------------------
    // fromConfig
    // -------------------------------------------------------------------------

    #[Test]
    public function it_resolves_a_channel_from_a_config_key(): void
    {
        config()->set('services.discord.channels.announcements', '777888999000111222');

        $channel = Channel::from(['id' => '777888999000111222', 'type' => ChannelType::GUILD_TEXT->value]);

        $this->discord->expects('getChannel')
            ->with('777888999000111222')
            ->andReturn($channel);

        $notifiable = NotifiableChannel::fromConfig('announcements', $this->discord);

        $this->assertInstanceOf(NotifiableChannel::class, $notifiable);
        $this->assertSame('777888999000111222', $notifiable->channel()->id);
    }

    #[Test]
    public function it_throws_a_runtime_exception_when_the_config_key_has_no_channel_id(): void
    {
        config()->set('services.discord.channels.missing_key', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/missing_key/');

        NotifiableChannel::fromConfig('missing_key', $this->discord);
    }

    #[Test]
    public function it_throws_a_runtime_exception_when_the_config_key_does_not_exist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/nonexistent/');

        NotifiableChannel::fromConfig('nonexistent', $this->discord);
    }
}
