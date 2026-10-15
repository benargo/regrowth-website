<?php

namespace Tests\Unit\Http\Integrations\Discord\Requests\Channels;

use App\Http\Integrations\Discord\Data\Channels\ChannelData;
use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Requests\Channels\GetChannelRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Discord\DiscordTestCase;

#[Group('discord-integration')]
class GetChannelRequestTest extends DiscordTestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_gets_the_channel_and_returns_channel_data(): void
    {
        Saloon::fake([GetChannelRequest::class => MockResponse::make(['id' => '111111111111111111', 'name' => 'raids', 'position' => 1])]);

        $channel = $this->makeConnector()->send(new GetChannelRequest('111111111111111111'))->dto();

        $this->assertInstanceOf(ChannelData::class, $channel);
        $this->assertSame('raids', $channel->name);

        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetChannelRequest
            && $request->getMethod() === Method::GET
            && $request->resolveEndpoint() === '/channels/111111111111111111');
    }

    #[Test]
    #[Group('happy-path')]
    public function it_caches_the_channel_response(): void
    {
        Saloon::fake([GetChannelRequest::class => MockResponse::make(['id' => '111111111111111111', 'name' => 'raids', 'position' => 1])]);
        $connector = $this->makeConnector();

        $connector->send(new GetChannelRequest('111111111111111111'));
        $second = $connector->send(new GetChannelRequest('111111111111111111'));

        $this->assertTrue($second->isCached());
        Saloon::mockClient()->assertSentCount(1, GetChannelRequest::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_does_not_cache_an_error_response(): void
    {
        Saloon::fake([GetChannelRequest::class => MockResponse::make(['message' => 'Missing Access', 'code' => 50001], 403)]);
        $connector = $this->makeConnector();

        $this->assertThrows(fn () => $connector->send(new GetChannelRequest('111111111111111111')), ApiException::class);
        $this->assertThrows(fn () => $connector->send(new GetChannelRequest('111111111111111111')), ApiException::class);

        Saloon::mockClient()->assertSentCount(2, GetChannelRequest::class);
    }
}
