<?php

namespace Tests\Feature\Jobs\Discord;

use App\Http\Integrations\Discord\Requests\Guilds\GetGuildRolesRequest;
use App\Jobs\Discord\SyncRoles;
use App\Jobs\Discord\SyncUser;
use App\Jobs\Discord\SyncUsers;
use App\Models\DiscordRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('auth')]
#[Group('discord-integration')]
class SyncRolesTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    #[Test]
    public function it_creates_new_roles_from_discord(): void
    {
        $this->fakeGuildRoles();

        SyncRoles::dispatchSync();

        $this->assertDatabaseCount('discord_roles', 3);
        $this->assertDatabaseHas('discord_roles', ['id' => '111111111111111111', 'name' => 'Officer', 'position' => 10]);
        $this->assertDatabaseHas('discord_roles', ['id' => '222222222222222222', 'name' => 'Raider', 'position' => 20]);
        $this->assertDatabaseHas('discord_roles', ['id' => '333333333333333333', 'name' => 'Member', 'position' => 30]);

        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetGuildRolesRequest
            && $request->resolveEndpoint() === '/guilds/829020506907869214/roles');
    }

    #[Test]
    public function it_updates_existing_roles(): void
    {
        DiscordRole::factory()->create([
            'id' => '111111111111111111',
            'name' => 'Old Name',
            'position' => 99,
        ]);

        $this->fakeGuildRoles();

        SyncRoles::dispatchSync();

        $this->assertDatabaseHas('discord_roles', [
            'id' => '111111111111111111',
            'name' => 'Officer',
            'position' => 10,
        ]);
    }

    #[Test]
    public function it_deletes_roles_no_longer_in_discord(): void
    {
        DiscordRole::factory()->create([
            'id' => '999999999999999999',
            'name' => 'Deleted Role',
            'position' => 50,
        ]);

        $this->fakeGuildRoles();

        SyncRoles::dispatchSync();

        $this->assertDatabaseMissing('discord_roles', ['id' => '999999999999999999']);
    }

    #[Test]
    public function it_excludes_the_everyone_role(): void
    {
        $this->fakeGuildRoles();

        SyncRoles::dispatchSync();

        $this->assertDatabaseMissing('discord_roles', ['id' => '000000000000000000']);
    }

    #[Test]
    public function it_preserves_is_visible_on_existing_roles(): void
    {
        DiscordRole::factory()->visible()->create([
            'id' => '111111111111111111',
            'name' => 'Officer',
            'position' => 10,
        ]);

        $this->fakeGuildRoles();

        SyncRoles::dispatchSync();

        $this->assertDatabaseHas('discord_roles', [
            'id' => '111111111111111111',
            'is_visible' => true,
        ]);
    }

    #[Test]
    public function it_does_not_dispatch_any_user_sync_jobs(): void
    {
        Bus::fake([SyncUser::class, SyncUsers::class]);
        User::factory()->create();
        $this->fakeGuildRoles();

        SyncRoles::dispatchSync();

        Bus::assertNothingBatched();
        Bus::assertNotDispatched(SyncUser::class);
        Bus::assertNotDispatched(SyncUsers::class);
    }

    #[Test]
    public function it_does_nothing_when_a_duplicate_is_skipped(): void
    {
        $this->fakeGuildRoles();

        $running = new SyncRoles;
        $overlap = collect($running->middleware())->first(fn (object $middleware): bool => $middleware instanceof WithoutOverlapping);
        Cache::lock($overlap->getLockKey($running), 120)->get();

        SyncRoles::dispatchSync();

        Saloon::assertNothingSent();
        $this->assertDatabaseCount('discord_roles', 0);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_releases_itself_when_discord_is_rate_limited(): void
    {
        $this->fakeDiscord([GetGuildRolesRequest::class => $this->makeDiscordRateLimitResponse(30.0)]);

        $job = new SyncRoles;
        $job->withFakeQueueInteractions();
        $this->runJobThroughMiddleware($job);

        $job->assertReleased(30);
        $this->assertDatabaseCount('discord_roles', 0);
    }

    // ==================== helpers ====================

    private function fakeGuildRoles(): void
    {
        $this->fakeDiscord([GetGuildRolesRequest::class => MockResponse::make([
            $this->makeDiscordRoleResponse('000000000000000000', '@everyone', 0),
            $this->makeDiscordRoleResponse('111111111111111111', 'Officer', 10),
            $this->makeDiscordRoleResponse('222222222222222222', 'Raider', 20),
            $this->makeDiscordRoleResponse('333333333333333333', 'Member', 30),
        ])]);
    }
}
