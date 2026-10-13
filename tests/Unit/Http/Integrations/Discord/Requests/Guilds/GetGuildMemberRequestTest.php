<?php

namespace Tests\Unit\Http\Integrations\Discord\Requests\Guilds;

use App\Http\Integrations\Discord\Data\Guilds\GuildMemberData;
use App\Http\Integrations\Discord\Exceptions\UserNotInGuildException;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildMemberRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Discord\DiscordTestCase;

#[Group('discord-integration')]
class GetGuildMemberRequestTest extends DiscordTestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_gets_the_guild_member(): void
    {
        Saloon::fake([GetGuildMemberRequest::class => MockResponse::make([
            'user' => ['id' => '111111111111111111', 'username' => 'thrall', 'discriminator' => '0', 'flags' => 0, 'public_flags' => 0],
            'nick' => 'Warchief',
            'roles' => ['222222222222222222'],
            'deaf' => false,
            'mute' => false,
            'flags' => 0,
        ])]);

        $member = $this->makeConnector()->send(new GetGuildMemberRequest('829020506907869214', '111111111111111111'))->dto();

        $this->assertInstanceOf(GuildMemberData::class, $member);
        $this->assertSame('Warchief', $member->nick);
        $this->assertSame(['222222222222222222'], $member->roles);

        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetGuildMemberRequest
            && $request->resolveEndpoint() === '/guilds/829020506907869214/members/111111111111111111');
    }

    #[Test]
    #[Group('error-handling')]
    public function it_throws_user_not_in_guild_for_unknown_member(): void
    {
        Saloon::fake([GetGuildMemberRequest::class => MockResponse::make(['message' => 'Unknown Member', 'code' => 10007], 404)]);

        $this->expectException(UserNotInGuildException::class);

        $this->makeConnector()->send(new GetGuildMemberRequest('829020506907869214', '111111111111111111'));
    }
}
