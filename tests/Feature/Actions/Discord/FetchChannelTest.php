<?php

namespace Tests\Feature\Actions\Discord;

use App\Actions\Discord\FetchChannel;
use App\Http\Integrations\Discord\Data\Channels\ChannelData;
use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Requests\Channels\GetChannelRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('discord-integration')]
class FetchChannelTest extends TestCase
{
    use MocksDiscordService;

    #[Test]
    #[Group('happy-path')]
    public function it_returns_the_channel(): void
    {
        $this->fakeDiscordChannel(id: '111111111111111111', name: 'raids', position: 3);

        $channel = FetchChannel::run('111111111111111111');

        $this->assertInstanceOf(ChannelData::class, $channel);
        $this->assertSame('111111111111111111', $channel->id);
        $this->assertSame('raids', $channel->name);
        $this->assertSame(3, $channel->position);
        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetChannelRequest
            && $request->resolveEndpoint() === '/channels/111111111111111111');
    }

    #[Test]
    #[Group('error-handling')]
    public function it_throws_on_a_discord_api_error(): void
    {
        $this->fakeDiscord([GetChannelRequest::class => $this->makeDiscordErrorResponse(403, 50001, 'Missing Access')]);

        $this->expectException(ApiException::class);

        FetchChannel::run('111111111111111111');
    }
}
