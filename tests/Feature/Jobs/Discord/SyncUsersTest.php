<?php

namespace Tests\Feature\Jobs\Discord;

use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Requests\Guilds\ListGuildMembersRequest;
use App\Jobs\Discord\SyncUsers;
use App\Models\Comment;
use App\Models\DiscordRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('auth')]
#[Group('discord-integration')]
class SyncUsersTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    public function it_updates_profile_data_for_existing_users(): void
    {
        User::factory()->create(['id' => '100000000000000001', 'nickname' => 'OldNick', 'guild_avatar' => 'old_avatar']);

        $this->fakeMembers([
            $this->member('100000000000000001', ['nick' => 'NewNick', 'avatar' => 'new_avatar']),
        ]);

        SyncUsers::dispatchSync();

        $user = User::find('100000000000000001');
        $this->assertSame('NewNick', $user->nickname);
        $this->assertSame('new_avatar', $user->guild_avatar);
    }

    #[Test]
    public function it_keeps_profile_fields_the_member_payload_omits(): void
    {
        User::factory()->create(['id' => '100000000000000001', 'nickname' => 'OldNick', 'guild_avatar' => 'old_avatar']);

        $this->fakeMembers([Arr::except($this->member('100000000000000001'), ['nick', 'avatar'])]);

        SyncUsers::dispatchSync();

        $user = User::find('100000000000000001');
        $this->assertSame('OldNick', $user->nickname);
        $this->assertSame('old_avatar', $user->guild_avatar);
    }

    #[Test]
    public function it_updates_the_user_avatar_hash(): void
    {
        User::factory()->create(['id' => '100000000000000001', 'avatar' => 'old_avatar']);

        $this->fakeMembers([
            array_replace_recursive($this->member('100000000000000001'), ['user' => ['avatar' => 'new_avatar']]),
        ]);

        SyncUsers::dispatchSync();

        $this->assertSame('new_avatar', User::find('100000000000000001')->avatar);
    }

    #[Test]
    public function it_updates_the_avatar_of_a_user_alongside_a_member_without_a_user(): void
    {
        User::factory()->create(['id' => '100000000000000001', 'avatar' => 'old_avatar']);

        $this->fakeMembers([
            array_replace_recursive($this->member('100000000000000001'), ['user' => ['avatar' => 'new_avatar']]),
            Arr::except($this->member('100000000000000002'), ['user']),
        ]);

        SyncUsers::dispatchSync();

        $this->assertSame('new_avatar', User::find('100000000000000001')->avatar);
        $this->assertDatabaseMissing('users', ['id' => '100000000000000002']);
    }

    #[Test]
    public function it_syncs_roles_and_ignores_unknown_role_ids(): void
    {
        $officer = DiscordRole::factory()->create(['id' => '111111111111111111']);
        $stale = DiscordRole::factory()->create(['id' => '222222222222222222']);
        $user = User::factory()->create(['id' => '100000000000000001']);
        $user->discordRoles()->attach($stale);

        $this->fakeMembers([
            $this->member('100000000000000001', ['roles' => ['111111111111111111', '999999999999999999']]),
        ]);

        SyncUsers::dispatchSync();

        $this->assertSame([$officer->id], $user->discordRoles()->pluck('id')->all());
    }

    #[Test]
    public function it_pages_through_every_member_with_the_correct_after_cursor(): void
    {
        User::factory()->create(['id' => '100000000000000500', 'nickname' => null]);
        User::factory()->create(['id' => '200000000000000000', 'nickname' => null]);

        $firstPage = $this->memberPage(start: 100000000000000001, count: 1000);
        $lastIdOnFirstPage = '100000000000001000';

        Saloon::fake([ListGuildMembersRequest::class => fn (PendingRequest $pending): MockResponse => match ((string) $pending->query()->get('after')) {
            '0' => MockResponse::make($firstPage),
            $lastIdOnFirstPage => MockResponse::make([$this->member('200000000000000000', ['nick' => 'PageTwo'])]),
        }]);

        SyncUsers::dispatchSync();

        Saloon::mockClient()->assertSentCount(2, ListGuildMembersRequest::class);
        $this->assertSame('PageTwo', User::find('200000000000000000')->nickname);
        $this->assertDatabaseHas('users', ['id' => '100000000000000500']);
    }

    #[Test]
    public function it_ignores_guild_members_without_a_local_user(): void
    {
        User::factory()->create(['id' => '100000000000000001']);

        $this->fakeMembers([
            $this->member('100000000000000001'),
            $this->member('100000000000000002'),
        ]);

        SyncUsers::dispatchSync();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['id' => '100000000000000002']);
    }

    #[Test]
    public function it_deletes_users_no_longer_in_the_guild(): void
    {
        User::factory()->create(['id' => '100000000000000001']);
        User::factory()->create(['id' => '100000000000000002']);

        $this->fakeMembers([$this->member('100000000000000001')]);

        SyncUsers::dispatchSync();

        $this->assertDatabaseHas('users', ['id' => '100000000000000001']);
        $this->assertDatabaseMissing('users', ['id' => '100000000000000002']);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_keeps_deleting_departed_users_when_one_cannot_be_deleted(): void
    {
        User::factory()->create(['id' => '100000000000000001']);
        $commenter = User::factory()->create(['id' => '100000000000000002']);
        User::factory()->create(['id' => '100000000000000003']);
        Comment::factory()->create(['user_id' => $commenter->id]);

        $this->fakeMembers([$this->member('100000000000000001')]);

        SyncUsers::dispatchSync();

        $this->assertDatabaseHas('users', ['id' => '100000000000000002']);
        $this->assertDatabaseMissing('users', ['id' => '100000000000000003']);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_deletes_nobody_when_the_member_list_is_empty(): void
    {
        User::factory()->create(['id' => '100000000000000001']);

        $this->fakeMembers([]);

        SyncUsers::dispatchSync();

        $this->assertDatabaseHas('users', ['id' => '100000000000000001']);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_changes_nothing_when_a_later_page_fails(): void
    {
        User::factory()->create(['id' => '100000000000000001', 'nickname' => 'OldNick']);
        User::factory()->create(['id' => '900000000000000000']);

        $firstPage = $this->memberPage(start: 100000000000000001, count: 1000);
        $firstPage[0]['nick'] = 'NewNick';

        Saloon::fake([ListGuildMembersRequest::class => fn (PendingRequest $pending): MockResponse => (string) $pending->query()->get('after') === '0'
            ? MockResponse::make($firstPage)
            : $this->makeDiscordErrorResponse(500, 0, 'Internal Server Error')]);

        $thrown = null;

        try {
            SyncUsers::dispatchSync();
        } catch (ApiException $exception) {
            $thrown = $exception;
        }

        $this->assertInstanceOf(ApiException::class, $thrown);
        $this->assertSame('OldNick', User::find('100000000000000001')->nickname);
        $this->assertDatabaseHas('users', ['id' => '900000000000000000']);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_releases_itself_when_discord_is_rate_limited(): void
    {
        User::factory()->create(['id' => '100000000000000001']);
        $this->fakeDiscord([ListGuildMembersRequest::class => $this->makeDiscordRateLimitResponse(30.0)]);

        $job = new SyncUsers;
        $job->withFakeQueueInteractions();
        $this->runJobThroughMiddleware($job);

        $job->assertReleased(30);
        $this->assertDatabaseHas('users', ['id' => '100000000000000001']);
        $this->assertTrue($job->retryUntil()->isFuture());
        $class = new ReflectionClass(SyncUsers::class);
        $this->assertSame(3, $class->getAttributes(MaxExceptions::class)[0]->newInstance()->maxExceptions);
        $this->assertSame(60, $class->getAttributes(Backoff::class)[0]->newInstance()->backoff);
    }

    #[Test]
    public function it_does_not_run_while_another_sync_is_in_progress(): void
    {
        $this->fakeMembers([]);

        $running = new SyncUsers;
        $overlap = collect($running->middleware())->first(fn (object $middleware): bool => $middleware instanceof WithoutOverlapping);
        Cache::lock($overlap->getLockKey($running), 120)->get();

        SyncUsers::dispatchSync();

        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_tags_itself_for_horizon(): void
    {
        $this->assertSame(['discord', 'discord:users'], (new SyncUsers)->tags());
    }

    // ==================== helpers ====================

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function member(string $userId, array $overrides = []): array
    {
        return array_merge($this->makeDiscordGuildMemberWithUser($userId, "user{$userId}"), $overrides);
    }

    /**
     * A page of members with consecutive user IDs starting at $start.
     *
     * @return array<int, array<string, mixed>>
     */
    private function memberPage(int $start, int $count): array
    {
        return array_map(fn (int $id): array => $this->member((string) $id), range($start, $start + $count - 1));
    }

    /**
     * Fake a single short page of members.
     *
     * @param  array<int, array<string, mixed>>  $members
     */
    private function fakeMembers(array $members): void
    {
        $this->fakeDiscord([ListGuildMembersRequest::class => MockResponse::make($members)]);
    }
}
