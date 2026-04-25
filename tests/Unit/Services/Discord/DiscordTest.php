<?php

namespace Tests\Unit\Services\Discord;

use App\Exceptions\MisconfigurationException;
use App\Services\Discord\Discord;
use App\Services\Discord\DiscordClient;
use App\Services\Discord\Enums\ChannelType;
use App\Services\Discord\Resources\Channel;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscordTest extends TestCase
{
    private DiscordClient&MockInterface $client;

    private Discord $discord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Mockery::mock(DiscordClient::class);
        $this->discord = new Discord($this->client, ['server_id' => '111222333444555666']);
    }

    // -------------------------------------------------------------------------
    // getChannel
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_a_channel_for_a_valid_channel_id(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'id' => '987654321098765432',
            'type' => ChannelType::GUILD_TEXT->value,
            'name' => 'general',
        ]);

        $this->client->expects('get')
            ->with('channels/987654321098765432')
            ->andReturn($response);

        $channel = $this->discord->getChannel('987654321098765432');

        $this->assertInstanceOf(Channel::class, $channel);
        $this->assertSame('987654321098765432', $channel->id);
        $this->assertSame(ChannelType::GUILD_TEXT, $channel->type);
        $this->assertSame('general', $channel->name);
    }

    #[Test]
    public function it_passes_the_channel_id_in_the_api_path(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'id' => '111000111000111000',
            'type' => ChannelType::GUILD_VOICE->value,
        ]);

        $this->client->expects('get')
            ->with('channels/111000111000111000')
            ->andReturn($response);

        $this->discord->getChannel('111000111000111000');
    }

    // -------------------------------------------------------------------------
    // listGuildChannels
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_a_collection_of_channels_for_the_configured_guild(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            ['id' => '111000111000111001', 'type' => ChannelType::GUILD_TEXT->value, 'name' => 'general'],
            ['id' => '111000111000111002', 'type' => ChannelType::GUILD_VOICE->value, 'name' => 'voice-chat'],
        ]);

        $this->client->expects('get')
            ->with('guilds/111222333444555666/channels')
            ->andReturn($response);

        $channels = $this->discord->listGuildChannels();

        $this->assertInstanceOf(Collection::class, $channels);
        $this->assertCount(2, $channels);
        $this->assertSame('111000111000111001', $channels->toArray()[0]['id']);
        $this->assertSame('111000111000111002', $channels->toArray()[1]['id']);
    }

    #[Test]
    public function it_uses_an_explicit_guild_id_when_provided(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            ['id' => '222000222000222001', 'type' => ChannelType::GUILD_TEXT->value, 'name' => 'announcements'],
        ]);

        $this->client->expects('get')
            ->with('guilds/999888777666555444/channels')
            ->andReturn($response);

        $channels = $this->discord->listGuildChannels('999888777666555444');

        $this->assertInstanceOf(Collection::class, $channels);
        $this->assertCount(1, $channels);
    }

    #[Test]
    public function it_throws_a_misconfiguration_exception_when_no_guild_id_is_configured_or_provided(): void
    {
        $discord = new Discord($this->client, []);

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessageMatches('/server_id/');

        $discord->listGuildChannels();
    }

    // -------------------------------------------------------------------------
    // config()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_throws_a_misconfiguration_exception_for_a_missing_required_config_key(): void
    {
        $discord = new Discord($this->client, []);

        // getChannel does not use config(), so we need a method that does.
        // We test the guard indirectly by constructing with an empty config and
        // asserting the exception message contains the missing key.
        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessageMatches('/server_id/');

        // Expose via a subclass so we can call the private method.
        $reflection = new \ReflectionClass($discord);
        $method = $reflection->getMethod('config');
        $method->setAccessible(true);
        $method->invoke($discord, 'server_id');
    }

    #[Test]
    public function it_returns_the_config_value_when_the_key_exists(): void
    {
        $discord = new Discord($this->client, ['server_id' => '999']);

        $reflection = new \ReflectionClass($discord);
        $method = $reflection->getMethod('config');
        $method->setAccessible(true);

        $this->assertSame('999', $method->invoke($discord, 'server_id'));
    }

    #[Test]
    public function it_returns_the_default_when_the_key_is_missing_and_a_default_is_supplied(): void
    {
        $discord = new Discord($this->client, []);

        $reflection = new \ReflectionClass($discord);
        $method = $reflection->getMethod('config');
        $method->setAccessible(true);

        $this->assertSame('fallback', $method->invoke($discord, 'missing_key', 'fallback'));
    }
}
