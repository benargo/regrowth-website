<?php

namespace Tests\Unit\Http\Integrations\Discord;

use App\Http\Integrations\Discord\DiscordCdnConnector;
use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Exceptions\AvatarNotFoundException;
use App\Http\Integrations\Discord\Requests\Cdn\FetchDefaultAvatarRequest;
use App\Http\Integrations\Discord\Requests\Cdn\FetchGuildMemberAvatarRequest;
use App\Http\Integrations\Discord\Requests\Cdn\FetchUserAvatarRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;

#[Group('discord-integration')]
class DiscordCdnConnectorTest extends DiscordTestCase
{
    #[Test]
    public function it_fetches_a_user_avatar(): void
    {
        $this->assertFetches(new FetchUserAvatarRequest('123', 'abc'), 'https://cdn.discordapp.com/avatars/123/abc.webp');
    }

    #[Test]
    public function it_fetches_a_guild_member_avatar(): void
    {
        $this->assertFetches(new FetchGuildMemberAvatarRequest('9', '123', 'abc'), 'https://cdn.discordapp.com/guilds/9/users/123/avatars/abc.webp');
    }

    #[Test]
    public function it_fetches_a_default_avatar(): void
    {
        $this->assertFetches(new FetchDefaultAvatarRequest(3), 'https://cdn.discordapp.com/embed/avatars/3.png');
    }

    #[Test]
    public function it_throws_avatar_not_found_on_404(): void
    {
        Saloon::fake([FetchUserAvatarRequest::class => MockResponse::make('', 404)]);

        $this->expectException(AvatarNotFoundException::class);

        app(DiscordCdnConnector::class)->send(new FetchUserAvatarRequest('123', 'abc'));
    }

    #[Test]
    public function it_throws_api_exception_on_server_error(): void
    {
        Saloon::fake([FetchUserAvatarRequest::class => MockResponse::make('', 500)]);

        $this->expectException(ApiException::class);

        app(DiscordCdnConnector::class)->send(new FetchUserAvatarRequest('123', 'abc'));
    }

    #[Test]
    public function it_sends_no_authorization_header(): void
    {
        Saloon::fake([FetchUserAvatarRequest::class => MockResponse::make('BYTES', 200)]);

        $response = app(DiscordCdnConnector::class)->send(new FetchUserAvatarRequest('123', 'abc'));

        $this->assertNull($response->getPendingRequest()->headers()->get('Authorization'));
    }

    private function assertFetches(Request $request, string $url): void
    {
        Saloon::fake([$request::class => MockResponse::make('BYTES', 200)]);

        $response = app(DiscordCdnConnector::class)->send($request);

        $this->assertSame('BYTES', $response->body());
        Saloon::assertSent(fn (Request $r, Response $res) => $res->getPendingRequest()->getUrl() === $url);
    }
}
