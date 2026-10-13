<?php

namespace Tests\Feature\Jobs;

use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildMemberRequest;
use App\Jobs\SyncDiscordUser;
use App\Models\DiscordRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('auth')]
#[Group('discord-integration')]
class SyncDiscordUserTest extends TestCase
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

        $this->fakeGuildMember([
            'nick' => 'TestNick',
            'avatar' => 'avatar_hash',
            'banner' => 'banner_hash',
            'roles' => ['111111111111111111'],
        ]);

        SyncDiscordUser::dispatchSync('100000000000000000');

        $user->refresh();
        $this->assertSame('TestNick', $user->nickname);
        $this->assertSame('avatar_hash', $user->guild_avatar);
        $this->assertSame('banner_hash', $user->banner);
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
            'banner' => 'old_banner',
        ]);

        $this->fakeGuildMember([
            'nick' => 'NewNick',
            'avatar' => 'new_avatar',
            'banner' => 'new_banner',
        ]);

        SyncDiscordUser::dispatchSync('100000000000000000');

        $user = User::find('100000000000000000');
        $this->assertSame('NewNick', $user->nickname);
        $this->assertSame('new_avatar', $user->guild_avatar);
        $this->assertSame('new_banner', $user->banner);
    }

    #[Test]
    public function it_syncs_a_guild_member(): void
    {
        $role = DiscordRole::factory()->create([
            'id' => '111111111111111111',
            'name' => 'Member',
            'position' => 30,
        ]);

        $user = User::factory()->create(['id' => '100000000000000000']);

        $this->fakeGuildMember(['nick' => 'ValidUser', 'roles' => ['111111111111111111']]);

        SyncDiscordUser::dispatchSync('100000000000000000');

        $user->refresh();
        $this->assertSame('ValidUser', $user->nickname);
        $this->assertTrue($user->discordRoles->contains($role));
    }

    #[Test]
    public function it_deletes_a_user_not_in_guild(): void
    {
        User::factory()->create(['id' => '100000000000000000']);

        Saloon::fake([GetGuildMemberRequest::class => $this->makeDiscordErrorResponse(404, 10007, 'Unknown Member')]);

        SyncDiscordUser::dispatchSync('100000000000000000');

        $this->assertDatabaseMissing('users', ['id' => '100000000000000000']);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_keeps_the_user_when_the_guild_is_unknown(): void
    {
        User::factory()->create(['id' => '100000000000000000']);

        Saloon::fake([GetGuildMemberRequest::class => $this->makeDiscordErrorResponse(404, 10004, 'Unknown Guild')]);

        try {
            SyncDiscordUser::dispatchSync('100000000000000000');
            $this->fail('Expected an ApiException for an unknown guild.');
        } catch (ApiException $exception) {
            $this->assertSame(10004, $exception->getDiscordCode());
        }

        $this->assertDatabaseHas('users', ['id' => '100000000000000000']);
    }

    #[Test]
    public function it_skips_execution_when_batch_is_cancelled(): void
    {
        $batch = Bus::batch([])->dispatch();
        $batch->cancel();

        $job = new SyncDiscordUser('100000000000000000');
        $job->batchId = $batch->id;
        dispatch_sync($job);

        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_only_syncs_recognized_role_ids(): void
    {
        DiscordRole::factory()->create([
            'id' => '111111111111111111',
            'name' => 'Officer',
            'position' => 10,
        ]);

        $user = User::factory()->create(['id' => '100000000000000000']);

        $this->fakeGuildMember(['roles' => ['111111111111111111', '999999999999999999']]);

        SyncDiscordUser::dispatchSync('100000000000000000');

        $user->refresh();
        $this->assertCount(1, $user->discordRoles);
        $this->assertTrue($user->discordRoles->contains('id', '111111111111111111'));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_releases_itself_when_discord_is_rate_limited(): void
    {
        User::factory()->create(['id' => '100000000000000000']);

        Saloon::fake([GetGuildMemberRequest::class => $this->makeDiscordRateLimitResponse(10.0)]);

        $job = new SyncDiscordUser('100000000000000000');
        $job->withFakeQueueInteractions();
        $this->runJobThroughMiddleware($job);

        $job->assertReleased(10);
        Saloon::mockClient()->assertSentCount(1, GetGuildMemberRequest::class);
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

    /**
     * Run the job through its middleware() and then call handle() through the container.
     */
    private function runJobThroughMiddleware(SyncDiscordUser $job): void
    {
        (new Pipeline($this->app))
            ->send($job)
            ->through($job->middleware())
            ->then(fn (SyncDiscordUser $job) => $this->app->call([$job, 'handle']));
    }
}
