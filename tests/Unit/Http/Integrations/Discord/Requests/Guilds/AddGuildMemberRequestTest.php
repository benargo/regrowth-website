<?php

namespace Tests\Unit\Http\Integrations\Discord\Requests\Guilds;

use App\Http\Integrations\Discord\Data\Guilds\GuildMemberData;
use App\Http\Integrations\Discord\Requests\Guilds\AddGuildMemberRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Discord\DiscordTestCase;

#[Group('discord-integration')]
class AddGuildMemberRequestTest extends DiscordTestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_puts_the_users_access_token_to_the_guild_member_endpoint(): void
    {
        Saloon::fake([AddGuildMemberRequest::class => MockResponse::make($this->memberBody(), 201)]);

        $this->makeConnector()->send(new AddGuildMemberRequest('829020506907869214', '111111111111111111', 'user-token'));

        Saloon::assertSent(fn (Request $request): bool => $request instanceof AddGuildMemberRequest
            && $request->getMethod() === Method::PUT
            && $request->resolveEndpoint() === '/guilds/829020506907869214/members/111111111111111111'
            && $request->body()->all() === ['access_token' => 'user-token']);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_returns_the_member_on_201(): void
    {
        Saloon::fake([AddGuildMemberRequest::class => MockResponse::make($this->memberBody(), 201)]);

        $member = $this->makeConnector()->send(new AddGuildMemberRequest('829020506907869214', '111111111111111111', 'user-token'))->dto();

        $this->assertInstanceOf(GuildMemberData::class, $member);
        $this->assertSame(['222222222222222222'], $member->roles);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_returns_null_on_an_empty_204(): void
    {
        Saloon::fake([AddGuildMemberRequest::class => MockResponse::make('', 204)]);

        $this->assertNull($this->makeConnector()->send(new AddGuildMemberRequest('829020506907869214', '111111111111111111', 'user-token'))->dto());
    }

    /**
     * @return array<string, mixed>
     */
    private function memberBody(): array
    {
        return [
            'user' => ['id' => '111111111111111111', 'username' => 'thrall', 'discriminator' => '0', 'flags' => 0, 'public_flags' => 0],
            'nick' => null,
            'roles' => ['222222222222222222'],
            'joined_at' => '2026-01-01T00:00:00+00:00',
            'deaf' => false,
            'mute' => false,
            'flags' => 0,
        ];
    }
}
