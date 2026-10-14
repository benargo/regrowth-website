<?php

namespace Tests\Feature\Jobs\Discord;

use App\Events\Broadcasts\DiscordMemberResolved;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildMemberRequest;
use App\Jobs\Discord\ResolveGuildMember;
use App\Models\DiscordRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('discord-integration')]
class ResolveGuildMemberTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([DiscordMemberResolved::class]);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_creates_the_user_from_guild_member_data_and_syncs_recognised_roles(): void
    {
        DiscordRole::factory()->create(['id' => '111111111111111111']);
        $this->fakeGuildMember();

        ResolveGuildMember::dispatchSync('999999999999999999', '100000000000000003');

        $user = User::findOrFail('999999999999999999');
        $this->assertSame('DiscordNick', $user->nickname);
        $this->assertSame('discorduser', $user->username);
        $this->assertSame(['111111111111111111'], $user->discordRoles()->pluck('id')->all());
        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetGuildMemberRequest
            && $request->resolveEndpoint() === '/guilds/829020506907869214/members/999999999999999999');
        $this->assertBroadcastStatus('ready');
    }

    #[Test]
    public function it_stores_null_profile_fields_when_the_member_payload_omits_them(): void
    {
        $this->fakeDiscord([GetGuildMemberRequest::class => MockResponse::make(
            Arr::except($this->guildMemberResponse(), ['nick', 'avatar', 'banner']),
        )]);

        ResolveGuildMember::dispatchSync('999999999999999999', '100000000000000003');

        $this->assertDatabaseHas('users', [
            'id' => '999999999999999999',
            'nickname' => null,
            'guild_avatar' => null,
            'banner' => null,
        ]);
    }

    #[Test]
    public function it_broadcasts_ready_for_an_existing_user_without_calling_discord(): void
    {
        $this->fakeDiscord([]);
        User::factory()->create(['id' => '999999999999999999']);

        ResolveGuildMember::dispatchSync('999999999999999999', '100000000000000003');

        Saloon::assertNothingSent();
        $this->assertBroadcastStatus('ready');
    }

    #[Test]
    public function it_keeps_the_user_another_job_created_first(): void
    {
        $this->fakeDiscord([GetGuildMemberRequest::class => fn (): MockResponse => tap(
            MockResponse::make($this->guildMemberResponse()),
            fn () => User::factory()->create(['id' => '999999999999999999', 'username' => 'firstjob']),
        )]);

        ResolveGuildMember::dispatchSync('999999999999999999', '100000000000000003');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['id' => '999999999999999999', 'username' => 'firstjob']);
        $this->assertBroadcastStatus('ready');
    }

    #[Test]
    #[Group('contract')]
    public function it_runs_on_the_high_queue(): void
    {
        Queue::fake([ResolveGuildMember::class]);

        ResolveGuildMember::dispatch('999999999999999999', '100000000000000003');

        Queue::assertPushedOn('high', ResolveGuildMember::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_broadcasts_not_in_guild_for_an_unknown_member(): void
    {
        $this->fakeDiscord([GetGuildMemberRequest::class => $this->makeDiscordErrorResponse(404, 10007, 'Unknown Member')]);

        ResolveGuildMember::dispatchSync('999999999999999999', '100000000000000003');

        $this->assertBroadcastStatus('not_in_guild');
        $this->assertDatabaseMissing('users', ['id' => '999999999999999999']);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_releases_itself_when_discord_is_rate_limited(): void
    {
        $this->fakeDiscord([GetGuildMemberRequest::class => $this->makeDiscordRateLimitResponse(6.0)]);

        $job = new ResolveGuildMember('999999999999999999', '100000000000000003');
        $job->withFakeQueueInteractions();
        $this->runJobThroughMiddleware($job);

        $job->assertReleased(6);
        Event::assertNotDispatched(DiscordMemberResolved::class);
        $this->assertDatabaseMissing('users', ['id' => '999999999999999999']);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_tells_the_officer_when_resolution_finally_fails(): void
    {
        (new ResolveGuildMember('999999999999999999', '100000000000000003'))->failed(new RuntimeException('Discord is down'));

        $this->assertBroadcastStatus('failed');
    }

    // ==================== helpers ====================

    private function fakeGuildMember(): void
    {
        $this->fakeDiscord([GetGuildMemberRequest::class => MockResponse::make($this->guildMemberResponse())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function guildMemberResponse(): array
    {
        return $this->makeDiscordGuildMemberResponse([
            'user' => ['id' => '999999999999999999', 'username' => 'discorduser', 'discriminator' => '0', 'avatar' => null, 'flags' => 0, 'public_flags' => 0],
            'nick' => 'DiscordNick',
            'roles' => ['111111111111111111', '888888888888888888'],
        ]);
    }

    private function assertBroadcastStatus(string $status): void
    {
        Event::assertDispatched(DiscordMemberResolved::class, fn (DiscordMemberResolved $event): bool => $event->userId === '100000000000000003'
            && $event->memberId === '999999999999999999'
            && $event->status === $status);
    }
}
