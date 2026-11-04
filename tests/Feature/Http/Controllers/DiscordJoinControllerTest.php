<?php

namespace Tests\Feature\Http\Controllers;

use App\Contracts\Actions\Discord\HoldsPendingGuildJoin;
use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Exceptions\RateLimitedException;
use App\Http\Integrations\Discord\Requests\Guilds\AddGuildMemberRequest;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildMemberRequest;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request as SaloonRequest;
use Saloon\Laravel\Facades\Saloon;
use Spatie\LaravelData\Exceptions\CannotCreateData;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;
use Throwable;

#[Group('auth')]
#[Group('discord-integration')]
class DiscordJoinControllerTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.discord.channels.chat', '222222222222222222');
        config()->set('services.discord.channels.introductions', '333333333333333333');
    }

    // ==================== show ====================

    #[Test]
    #[Group('authorization')]
    public function a_guest_is_sent_to_login_with_discord_as_the_intended_url(): void
    {
        $response = $this->get('/discord');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('url.intended', route('discord'));
    }

    #[Test]
    #[Group('authorization')]
    public function a_guest_with_a_query_string_gets_the_same_intended_url(): void
    {
        $this->get('/discord?utm_source=forum')->assertSessionHas('url.intended', route('discord'));
    }

    #[Test]
    #[DataProvider('chatRoles')]
    #[Group('happy-path')]
    public function a_member_with_a_chat_role_goes_to_chat(string $roleId): void
    {
        $this->actingAs(User::factory()->create());
        $this->fakeMemberWithRoles(['829022020301094922', $roleId]);

        $this->get('/discord')->assertRedirect('https://discord.com/channels/829020506907869214/222222222222222222');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function chatRoles(): array
    {
        return [
            'classic member' => ['1549712849394335774'],
            'anniversary member' => ['1549712327497228338'],
            'forever member' => ['1548998062163107871'],
        ];
    }

    #[Test]
    #[Group('happy-path')]
    public function a_member_without_a_chat_role_goes_to_introductions(): void
    {
        $this->actingAs(User::factory()->create());
        $this->fakeMemberWithRoles(['829022020301094922']);

        $this->get('/discord')->assertRedirect('https://discord.com/channels/829020506907869214/333333333333333333');
    }

    #[Test]
    #[DataProvider('unconfiguredChannels')]
    #[Group('error-handling')]
    public function a_member_goes_to_the_guild_url_with_a_warning_when_the_channel_is_not_configured(?string $channelId): void
    {
        Log::spy();
        config()->set('services.discord.channels.introductions', $channelId);
        $this->actingAs(User::factory()->create());
        $this->fakeMemberWithRoles([]);

        $this->get('/discord')->assertRedirect('https://discord.com/channels/829020506907869214');

        Log::shouldHaveReceived('warning')->once();
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function unconfiguredChannels(): array
    {
        return ['null' => [null], 'empty' => ['']];
    }

    #[Test]
    #[Group('happy-path')]
    public function an_inertia_visit_reaches_discord_with_a_location_response(): void
    {
        $this->actingAs(User::factory()->create());
        $this->fakeMemberWithRoles([]);

        $response = $this->get('/discord', $this->inertiaHeaders());

        $response->assertStatus(409);
        $this->assertStringStartsWith('https://discord.com/channels/', $response->headers->get('X-Inertia-Location'));
    }

    #[Test]
    #[Group('happy-path')]
    public function a_user_viewing_as_a_role_is_looked_up_by_their_own_discord_id_and_stays_logged_in(): void
    {
        $viewAsUser = User::factory()->create();
        $this->actingAs($viewAsUser);
        $this->fakeMemberWithRoles([]);

        $response = $this->withSession(['impersonating_user_id' => '444444444444444444'])->get('/discord');

        $response->assertRedirect('https://discord.com/channels/'.config('services.discord.server_id').'/333333333333333333');
        $response->assertSessionHas('impersonating_user_id', '444444444444444444');
        $this->assertAuthenticatedAs($viewAsUser);
        Saloon::assertSent(fn (SaloonRequest $request): bool => str_ends_with($request->resolveEndpoint(), '/members/444444444444444444'));
    }

    #[Test]
    #[Group('happy-path')]
    public function a_logged_in_user_who_left_the_guild_is_logged_out_and_sent_to_login(): void
    {
        $this->actingAs(User::factory()->create());
        $this->fakeDiscord([GetGuildMemberRequest::class => $this->makeDiscordErrorResponse(404, 10007, 'Unknown Member')]);

        $response = $this->get('/discord');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('url.intended', route('discord'));
        $this->assertGuest();
    }

    #[Test]
    #[Group('happy-path')]
    public function a_pending_join_renders_the_consent_page_and_calls_nothing(): void
    {
        Saloon::fake([]);

        $response = $this->withSession([HoldsPendingGuildJoin::SESSION_KEY => $this->pendingJoin()])->get('/discord');

        $response->assertInertia(fn (Assert $page) => $page->component('Discord/Join'));
        Saloon::assertNothingSent();
    }

    #[Test]
    #[Group('error-handling')]
    public function a_discord_failure_shows_the_unavailable_page_and_reports(): void
    {
        Exceptions::fake();
        $this->actingAs(User::factory()->create());
        $this->fakeDiscord([GetGuildMemberRequest::class => $this->makeDiscordErrorResponse(500, 0, 'Internal Server Error')]);

        $response = $this->get('/discord');

        $response->assertStatus(500);
        Exceptions::assertReported(ApiException::class);
    }

    #[Test]
    #[Group('contract')]
    public function error_pages_link_to_the_discord_route_marked_nofollow(): void
    {
        $response = $this->get('/a-page-that-does-not-exist');

        $response->assertNotFound();
        $response->assertSee('href="'.route('discord').'"', false);
        $response->assertSee('rel="nofollow noopener noreferrer"', false);
    }

    #[Test]
    #[Group('contract')]
    public function responses_are_not_indexed_or_stored(): void
    {
        $response = $this->get('/discord');

        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    // ==================== store ====================

    #[Test]
    #[Group('happy-path')]
    public function confirming_adds_saves_and_logs_in_the_user_then_goes_to_introductions(): void
    {
        $this->fakeDiscord([AddGuildMemberRequest::class => MockResponse::make($this->makeDiscordGuildMemberResponse(), 201)]);

        $response = $this->withSession([HoldsPendingGuildJoin::SESSION_KEY => $this->pendingJoin()])->post('/discord');

        $response->assertRedirect('https://discord.com/channels/829020506907869214/333333333333333333');
        $response->assertSessionMissing(HoldsPendingGuildJoin::SESSION_KEY);
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['id' => '123456789012345678', 'username' => 'testuser']);
        $guard = Auth::guard();
        $this->assertInstanceOf(SessionGuard::class, $guard);
        $response->assertCookie($guard->getRecallerName());
    }

    #[Test]
    #[Group('happy-path')]
    public function a_user_who_joined_another_way_lands_by_their_real_roles(): void
    {
        $this->fakeDiscord([
            AddGuildMemberRequest::class => MockResponse::make('', 204),
            GetGuildMemberRequest::class => MockResponse::make($this->makeDiscordGuildMemberResponse(['roles' => ['1549712849394335774']])),
        ]);

        $response = $this->withSession([HoldsPendingGuildJoin::SESSION_KEY => $this->pendingJoin()])->post('/discord');

        $response->assertRedirect('https://discord.com/channels/829020506907869214/222222222222222222');
    }

    #[Test]
    #[Group('happy-path')]
    public function an_inertia_consent_submit_reaches_discord_with_a_location_response(): void
    {
        $this->fakeDiscord([AddGuildMemberRequest::class => MockResponse::make($this->makeDiscordGuildMemberResponse(), 201)]);

        $response = $this->withSession([HoldsPendingGuildJoin::SESSION_KEY => $this->pendingJoin()])
            ->post('/discord', headers: $this->inertiaHeaders());

        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', 'https://discord.com/channels/829020506907869214/333333333333333333');
    }

    #[Test]
    #[Group('authorization')]
    public function values_in_the_request_are_ignored(): void
    {
        $this->fakeDiscord([AddGuildMemberRequest::class => MockResponse::make($this->makeDiscordGuildMemberResponse(), 201)]);

        $this->withSession([HoldsPendingGuildJoin::SESSION_KEY => $this->pendingJoin()])
            ->post('/discord', ['user_id' => '999999999999999999', 'token' => 'attacker-token']);

        Saloon::assertSent(fn (SaloonRequest $request): bool => $request instanceof AddGuildMemberRequest
            && $request->resolveEndpoint() === '/guilds/829020506907869214/members/123456789012345678'
            && $request->body()->get('access_token') === 'user-token');
    }

    #[Test]
    #[Group('authorization')]
    public function without_a_pending_join_it_redirects_to_discord_and_adds_nobody(): void
    {
        Saloon::fake([]);

        $this->post('/discord')->assertRedirect(route('discord'));

        Saloon::assertNothingSent();
    }

    #[Test]
    #[DataProvider('refusedJoins')]
    #[Group('error-handling')]
    public function a_refused_join_goes_home_with_its_message_and_clears_the_session(int $status, int $code, string $message): void
    {
        $this->fakeDiscord([AddGuildMemberRequest::class => $this->makeDiscordErrorResponse($status, $code, 'Refused')]);

        $response = $this->withSession([HoldsPendingGuildJoin::SESSION_KEY => $this->pendingJoin()])->post('/discord');

        $response->assertRedirect('/');
        $response->assertSessionHas('error', $message);
        $response->assertSessionMissing(HoldsPendingGuildJoin::SESSION_KEY);
        $this->assertGuest();
    }

    /**
     * @return array<string, array{int, int, string}>
     */
    public static function refusedJoins(): array
    {
        return [
            'banned' => [403, 40007, "You can't be added to the Regrowth Discord server."],
            'server limit' => [400, 30001, "You're in too many Discord servers. Leave one and try again."],
        ];
    }

    #[Test]
    #[Group('error-handling')]
    public function an_invalid_token_is_reported_and_restarts_sign_in(): void
    {
        Exceptions::fake();
        $this->fakeDiscord([AddGuildMemberRequest::class => $this->makeDiscordErrorResponse(403, 50025, 'Invalid OAuth2 access token')]);

        $response = $this->withSession([HoldsPendingGuildJoin::SESSION_KEY => $this->pendingJoin()])->post('/discord');

        $response->assertRedirect(route('discord'));
        $response->assertSessionMissing(HoldsPendingGuildJoin::SESSION_KEY);
        Exceptions::assertReported(ApiException::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function a_member_body_that_cannot_be_read_gives_the_generic_error(): void
    {
        Exceptions::fake();
        $body = $this->makeDiscordGuildMemberResponse();
        unset($body['roles']);
        $this->fakeDiscord([AddGuildMemberRequest::class => MockResponse::make($body, 201)]);

        $response = $this->withSession([HoldsPendingGuildJoin::SESSION_KEY => $this->pendingJoin()])->post('/discord');

        $response->assertRedirect('/');
        $response->assertSessionHas('error', 'Failed to add you to the Regrowth Discord server. Please try again.');
        Exceptions::assertReported(CannotCreateData::class);
    }

    #[Test]
    #[DataProvider('unexpectedFailures')]
    #[Group('error-handling')]
    public function an_unexpected_failure_is_reported_and_goes_home_with_the_generic_error(MockResponse $mockResponse, string $exceptionClass): void
    {
        Exceptions::fake();
        $this->fakeDiscord([AddGuildMemberRequest::class => $mockResponse]);

        $response = $this->withSession([HoldsPendingGuildJoin::SESSION_KEY => $this->pendingJoin()])->post('/discord');

        $response->assertRedirect('/');
        $response->assertSessionHas('error', 'Failed to add you to the Regrowth Discord server. Please try again.');
        $response->assertSessionMissing(HoldsPendingGuildJoin::SESSION_KEY);
        Exceptions::assertReported($exceptionClass);
    }

    /**
     * @return array<string, array{MockResponse, class-string<Throwable>}>
     */
    public static function unexpectedFailures(): array
    {
        return [
            'missing permissions' => [MockResponse::make(['message' => 'Missing Permissions', 'code' => 50013], 403), ApiException::class],
            'rate limited' => [MockResponse::make(['message' => 'You are being rate limited.', 'retry_after' => 5, 'global' => false], 429), RateLimitedException::class],
        ];
    }

    #[Test]
    #[Group('error-handling')]
    public function a_token_that_cannot_be_decrypted_gives_the_generic_error(): void
    {
        Exceptions::fake();
        Saloon::fake([]);

        $response = $this->withSession([HoldsPendingGuildJoin::SESSION_KEY => ['user_id' => '123456789012345678', 'token' => 'not-encrypted']])
            ->post('/discord');

        $response->assertRedirect('/');
        $response->assertSessionHas('error', 'Failed to add you to the Regrowth Discord server. Please try again.');
        Saloon::assertNothingSent();
        Exceptions::assertReported(DecryptException::class);
    }

    // ==================== destroy ====================

    #[Test]
    #[Group('happy-path')]
    public function declining_clears_the_pending_join_and_logs_nobody_in(): void
    {
        Saloon::fake([]);

        $response = $this->withSession([HoldsPendingGuildJoin::SESSION_KEY => $this->pendingJoin()])->delete('/discord');

        $response->assertRedirect('/');
        $response->assertSessionMissing(HoldsPendingGuildJoin::SESSION_KEY);
        $this->assertGuest();
        Saloon::assertNothingSent();
    }

    // ==================== helpers ====================

    /**
     * A pending join for the given user and token, as stored in the session.
     *
     * @return array{user_id: string, token: string}
     */
    private function pendingJoin(string $userId = '123456789012345678', string $token = 'user-token'): array
    {
        return ['user_id' => $userId, 'token' => Crypt::encryptString($token)];
    }

    /**
     * Fake the guild member lookup with a member holding the given roles.
     *
     * @param  array<int, string>  $roles
     */
    private function fakeMemberWithRoles(array $roles): void
    {
        $this->fakeDiscord([GetGuildMemberRequest::class => MockResponse::make($this->makeDiscordGuildMemberResponse(['roles' => $roles]))]);
    }

    /**
     * The headers of an Inertia visit at the current asset version.
     *
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/discord')),
        ];
    }
}
