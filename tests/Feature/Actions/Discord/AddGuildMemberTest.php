<?php

namespace Tests\Feature\Actions\Discord;

use App\Actions\Discord\AddGuildMember;
use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Requests\Guilds\AddGuildMemberRequest;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildMemberRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('discord-integration')]
class AddGuildMemberTest extends TestCase
{
    use MocksDiscordService;

    #[Test]
    #[Group('happy-path')]
    public function it_returns_the_new_member_from_the_configured_server(): void
    {
        $this->fakeDiscord([AddGuildMemberRequest::class => MockResponse::make($this->makeDiscordGuildMemberResponse([
            'roles' => ['111111111111111111'],
        ]), 201)]);

        $member = AddGuildMember::run('999999999999999999', 'user-token');

        $this->assertSame(['111111111111111111'], $member->roles);
        Saloon::assertSent(fn (Request $request): bool => $request instanceof AddGuildMemberRequest
            && $request->resolveEndpoint() === '/guilds/829020506907869214/members/999999999999999999'
            && $request->body()->get('access_token') === 'user-token');
        Saloon::assertNotSent(GetGuildMemberRequest::class);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_fetches_the_member_when_discord_answers_204(): void
    {
        $this->fakeDiscord([
            AddGuildMemberRequest::class => MockResponse::make('', 204),
            GetGuildMemberRequest::class => MockResponse::make($this->makeDiscordGuildMemberResponse([
                'roles' => ['1549712849394335774'],
            ])),
        ]);

        $this->assertSame(['1549712849394335774'], AddGuildMember::run('999999999999999999', 'user-token')->roles);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_fetches_the_member_when_the_response_has_no_user(): void
    {
        $body = $this->makeDiscordGuildMemberResponse();
        unset($body['user']);

        $this->fakeDiscord([
            AddGuildMemberRequest::class => MockResponse::make($body, 201),
            GetGuildMemberRequest::class => MockResponse::make($this->makeDiscordGuildMemberResponse()),
        ]);

        AddGuildMember::run('999999999999999999', 'user-token');

        Saloon::assertSent(GetGuildMemberRequest::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_surfaces_the_discord_error_code(): void
    {
        $this->fakeDiscord([AddGuildMemberRequest::class => $this->makeDiscordErrorResponse(403, 40007, 'User banned from guild')]);

        try {
            AddGuildMember::run('999999999999999999', 'user-token');
            $this->fail('Expected an ApiException.');
        } catch (ApiException $exception) {
            $this->assertSame(40007, $exception->discordCode);
        }
    }

    #[Test]
    #[Group('error-handling')]
    public function it_keeps_the_token_out_of_the_exception_trace(): void
    {
        $ignoreArgs = ini_set('zend.exception_ignore_args', '0');
        $this->beforeApplicationDestroyed(fn () => ini_set('zend.exception_ignore_args', (string) $ignoreArgs));

        $this->fakeDiscord([AddGuildMemberRequest::class => $this->makeDiscordErrorResponse(403, 50013, 'Missing Permissions')]);

        try {
            AddGuildMember::run('999999999999999999', 'user-token');
            $this->fail('Expected an ApiException.');
        } catch (ApiException $exception) {
            $arguments = collect($exception->getTrace())->flatMap(fn (array $frame): array => $frame['args'] ?? []);

            $this->assertNotContains('user-token', $arguments);
        }
    }
}
