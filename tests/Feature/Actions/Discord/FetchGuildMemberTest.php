<?php

namespace Tests\Feature\Actions\Discord;

use App\Actions\Discord\FetchGuildMember;
use App\Http\Integrations\Discord\Data\Guilds\GuildMemberData;
use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Exceptions\UserNotInGuildException;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildMemberRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('discord-integration')]
class FetchGuildMemberTest extends TestCase
{
    use MocksDiscordService;

    #[Test]
    #[Group('happy-path')]
    public function it_returns_the_guild_member_from_the_configured_server(): void
    {
        $this->fakeDiscord([GetGuildMemberRequest::class => MockResponse::make($this->makeDiscordGuildMemberResponse([
            'nick' => 'DiscordNick',
            'roles' => ['111111111111111111'],
        ]))]);

        $member = FetchGuildMember::run('999999999999999999');

        $this->assertInstanceOf(GuildMemberData::class, $member);
        $this->assertSame('DiscordNick', $member->nick);
        $this->assertSame(['111111111111111111'], $member->roles);
        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetGuildMemberRequest
            && $request->resolveEndpoint() === '/guilds/829020506907869214/members/999999999999999999');
    }

    #[Test]
    #[Group('error-handling')]
    public function it_throws_when_the_user_is_not_in_the_guild(): void
    {
        $this->fakeDiscord([GetGuildMemberRequest::class => $this->makeDiscordErrorResponse(404, 10007, 'Unknown Member')]);

        $this->expectException(UserNotInGuildException::class);

        FetchGuildMember::run('999999999999999999');
    }

    #[Test]
    #[Group('error-handling')]
    public function it_throws_on_a_discord_api_error(): void
    {
        $this->fakeDiscord([GetGuildMemberRequest::class => $this->makeDiscordErrorResponse(403, 50001, 'Missing Access')]);

        $this->expectException(ApiException::class);

        FetchGuildMember::run('999999999999999999');
    }
}
