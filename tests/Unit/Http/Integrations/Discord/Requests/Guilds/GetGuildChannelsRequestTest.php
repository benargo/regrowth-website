<?php

namespace Tests\Unit\Http\Integrations\Discord\Requests\Guilds;

use App\Http\Integrations\Discord\Data\Channels\ChannelData;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildChannelsRequest;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Discord\DiscordTestCase;

#[Group('discord-integration')]
class GetGuildChannelsRequestTest extends DiscordTestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_lists_the_guild_channels_as_a_collection_of_channel_data(): void
    {
        Saloon::fake([GetGuildChannelsRequest::class => MockResponse::make([
            ['id' => '100000000000000001', 'name' => 'raids'],
            ['id' => '100000000000000002', 'name' => 'general'],
        ])]);

        $channels = $this->makeConnector()
            ->send(new GetGuildChannelsRequest('829020506907869214'))
            ->dto();

        $this->assertInstanceOf(Collection::class, $channels);
        $this->assertContainsOnlyInstancesOf(ChannelData::class, $channels);
        $this->assertSame(['100000000000000001', '100000000000000002'], $channels->pluck('id')->all());

        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetGuildChannelsRequest
            && $request->resolveEndpoint() === '/guilds/829020506907869214/channels');
    }
}
