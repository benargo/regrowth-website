<?php

namespace Tests\Unit\Http\Integrations\Discord\Requests\Guilds;

use App\Http\Integrations\Discord\Data\Guilds\EmojiData;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildEmojisRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Discord\DiscordTestCase;

#[Group('discord-integration')]
class GetGuildEmojisRequestTest extends DiscordTestCase
{
    #[Test]
    public function it_lists_the_guild_emojis(): void
    {
        Saloon::fake([GetGuildEmojisRequest::class => MockResponse::make([
            ['id' => '111111111111111111', 'name' => 'regrowth', 'roles' => [], 'require_colons' => true, 'managed' => false, 'animated' => false, 'available' => true],
        ])]);

        $emojis = $this->makeConnector()
            ->send(new GetGuildEmojisRequest('829020506907869214'))
            ->dto();

        $this->assertContainsOnlyInstancesOf(EmojiData::class, $emojis);
        $this->assertSame('regrowth', $emojis->first()->name);

        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetGuildEmojisRequest
            && $request->resolveEndpoint() === '/guilds/829020506907869214/emojis');
    }
}
