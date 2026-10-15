<?php

namespace Tests\Feature\Jobs\Discord;

use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildMemberRequest;
use App\Jobs\Discord\SyncUser;
use App\Models\DiscordRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('auth')]
#[Group('discord-integration')]
class SyncUserTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    #[Test]
    public function it_syncs_roles_for_a_guild_member(): void
    {
        $role = DiscordRole::factory()->create([
            'id' => '111111111111111111',
            'name' => 'Officer',
            'position' => 10,
        ]);

        $user = User::factory()->create(['id' => '100000000000000000']);

        $this->fakeGuildMember(['roles' => ['111111111111111111']]);

        SyncUser::dispatchSync('100000000000000000');

        $user->refresh();
        $this->assertTrue($user->discordRoles->contains($role));

        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetGuildMemberRequest
            && str_ends_with($request->resolveEndpoint(), '/members/100000000000000000'));
    }

    #[Test]
    public function it_updates_user_profile_data(): void
    {
        User::factory()->create([
            'id' => '100000000000000000',
            'nickname' => 'OldNick',
            'guild_avatar' => 'old_avatar',
        ]);

        $this->fakeGuildMember([
            'nick' => 'NewNick',
            'avatar' => 'new_avatar',
        ]);

        SyncUser::dispatchSync('100000000000000000');

        $user = User::find('100000000000000000');
        $this->assertSame('NewNick', $user->nickname);
        $this->assertSame('new_avatar', $user->guild_avatar);
    }

    #[Test]
    public function it_deletes_a_user_not_in_guild(): void
    {
        User::factory()->create(['id' => '100000000000000000']);

        Saloon::fake([GetGuildMemberRequest::class => $this->makeDiscordErrorResponse(404, 10007, 'Unknown Member')]);

        SyncUser::dispatchSync('100000000000000000');

        $this->assertDatabaseMissing('users', ['id' => '100000000000000000']);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_keeps_the_user_when_the_guild_is_unknown(): void
    {
        User::factory()->create(['id' => '100000000000000000']);

        Saloon::fake([GetGuildMemberRequest::class => $this->makeDiscordErrorResponse(404, 10004, 'Unknown Guild')]);

        try {
            SyncUser::dispatchSync('100000000000000000');
            $this->fail('Expected an ApiException for an unknown guild.');
        } catch (ApiException $exception) {
            $this->assertSame(10004, $exception->getDiscordCode());
        }

        $this->assertDatabaseHas('users', ['id' => '100000000000000000']);
    }

    #[Test]
    public function it_skips_execution_when_batch_is_cancelled(): void
    {
        User::factory()->create(['id' => '100000000000000000']);
        $this->fakeGuildMember([]);

        $batch = Bus::batch([])->dispatch();
        $batch->cancel();

        $job = new SyncUser('100000000000000000');
        $job->batchId = $batch->id;
        dispatch_sync($job);

        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_does_not_call_discord_when_the_user_no_longer_exists(): void
    {
        $this->fakeGuildMember([]);

        SyncUser::dispatchSync('100000000000000000');

        Saloon::assertNothingSent();
        $this->assertDatabaseCount('users', 0);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_releases_itself_without_spending_a_retry_when_discord_is_rate_limited(): void
    {
        User::factory()->create(['id' => '100000000000000000']);

        Saloon::fake([GetGuildMemberRequest::class => $this->makeDiscordRateLimitResponse(10.0)]);

        $job = new SyncUser('100000000000000000');
        $job->withFakeQueueInteractions();
        $this->runJobThroughMiddleware($job);

        $job->assertReleased(10);
        $this->assertTrue($job->retryUntil()->isFuture());
        $class = new ReflectionClass(SyncUser::class);
        $this->assertSame(3, $class->getAttributes(MaxExceptions::class)[0]->newInstance()->maxExceptions);
        $this->assertSame(60, $class->getAttributes(Backoff::class)[0]->newInstance()->backoff);
        $this->assertEmpty($class->getAttributes(Tries::class));
        Saloon::mockClient()->assertSentCount(1, GetGuildMemberRequest::class);
    }

    #[Test]
    public function it_does_not_block_a_different_user_while_one_user_is_syncing(): void
    {
        User::factory()->create(['id' => '100000000000000000']);
        User::factory()->create(['id' => '200000000000000000']);

        $this->fakeGuildMember([]);

        $syncing = new SyncUser('100000000000000000');
        $overlap = collect($syncing->middleware())->first(fn (object $middleware): bool => $middleware instanceof WithoutOverlapping);
        Cache::lock($overlap->getLockKey($syncing), 120)->get();

        $this->runJobThroughMiddleware(new SyncUser('100000000000000000'));
        $this->runJobThroughMiddleware(new SyncUser('200000000000000000'));

        Saloon::mockClient()->assertSentCount(1, GetGuildMemberRequest::class);
        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetGuildMemberRequest
            && str_ends_with($request->resolveEndpoint(), '/members/200000000000000000'));
    }

    #[Test]
    public function it_keeps_profile_fields_the_member_payload_omits(): void
    {
        User::factory()->create([
            'id' => '100000000000000000',
            'nickname' => 'OldNick',
            'guild_avatar' => 'old_avatar',
        ]);

        Saloon::fake([
            GetGuildMemberRequest::class => MockResponse::make(
                Arr::except($this->makeDiscordGuildMemberResponse(), ['nick', 'avatar', 'banner']),
            ),
        ]);

        SyncUser::dispatchSync('100000000000000000');

        $user = User::find('100000000000000000');
        $this->assertSame('OldNick', $user->nickname);
        $this->assertSame('old_avatar', $user->guild_avatar);
    }

    #[Test]
    public function it_clears_profile_fields_the_member_payload_sets_to_null(): void
    {
        User::factory()->create([
            'id' => '100000000000000000',
            'nickname' => 'OldNick',
            'guild_avatar' => 'old_avatar',
        ]);

        $this->fakeGuildMember(['nick' => null, 'avatar' => null, 'banner' => null]);

        SyncUser::dispatchSync('100000000000000000');

        $user = User::find('100000000000000000');
        $this->assertNull($user->nickname);
        $this->assertNull($user->guild_avatar);
    }

    // ==================== helpers ====================

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeGuildMember(array $overrides): void
    {
        Saloon::fake([
            GetGuildMemberRequest::class => MockResponse::make($this->makeDiscordGuildMemberResponse($overrides)),
        ]);
    }
}
