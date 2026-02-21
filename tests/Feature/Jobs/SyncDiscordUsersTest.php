<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncDiscordUsers;
use App\Models\DiscordRole;
use App\Models\User;
use App\Services\Discord\DiscordGuildService;
use App\Services\Discord\Exceptions\UserNotInGuildException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SyncDiscordUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_roles_for_guild_members(): void
    {
        $role = DiscordRole::factory()->create([
            'id' => '111111111111111111',
            'name' => 'Officer',
            'position' => 10,
        ]);

        $user = User::factory()->create(['id' => '100000000000000000']);

        $this->mock(DiscordGuildService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getGuildMember')
                ->with('100000000000000000')
                ->once()
                ->andReturn([
                    'nick' => 'TestNick',
                    'avatar' => 'avatar_hash',
                    'banner' => 'banner_hash',
                    'roles' => ['111111111111111111'],
                ]);
        });

        SyncDiscordUsers::dispatchSync();

        $user->refresh();
        $this->assertSame('TestNick', $user->nickname);
        $this->assertSame('avatar_hash', $user->guild_avatar);
        $this->assertSame('banner_hash', $user->banner);
        $this->assertTrue($user->discordRoles->contains($role));
    }

    public function test_it_updates_user_profile_data(): void
    {
        User::factory()->create([
            'id' => '100000000000000000',
            'nickname' => 'OldNick',
            'guild_avatar' => 'old_avatar',
            'banner' => 'old_banner',
        ]);

        $this->mock(DiscordGuildService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getGuildMember')
                ->with('100000000000000000')
                ->once()
                ->andReturn([
                    'nick' => 'NewNick',
                    'avatar' => 'new_avatar',
                    'banner' => 'new_banner',
                    'roles' => [],
                ]);
        });

        SyncDiscordUsers::dispatchSync();

        $user = User::find('100000000000000000');
        $this->assertSame('NewNick', $user->nickname);
        $this->assertSame('new_avatar', $user->guild_avatar);
        $this->assertSame('new_banner', $user->banner);
    }

    public function test_it_deletes_users_not_in_guild(): void
    {
        User::factory()->create(['id' => '100000000000000000']);

        $this->mock(DiscordGuildService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getGuildMember')
                ->with('100000000000000000')
                ->once()
                ->andThrow(new UserNotInGuildException('User not in guild'));
        });

        SyncDiscordUsers::dispatchSync();

        $this->assertDatabaseMissing('users', ['id' => '100000000000000000']);
    }

    public function test_it_skips_users_on_api_error_without_deleting(): void
    {
        User::factory()->create(['id' => '100000000000000000']);

        $this->mock(DiscordGuildService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getGuildMember')
                ->with('100000000000000000')
                ->once()
                ->andThrow(new \RuntimeException('Internal Server Error'));
        });

        SyncDiscordUsers::dispatchSync();

        $this->assertDatabaseHas('users', ['id' => '100000000000000000']);
    }

    public function test_it_handles_mixed_user_scenarios(): void
    {
        $role = DiscordRole::factory()->create([
            'id' => '111111111111111111',
            'name' => 'Member',
            'position' => 30,
        ]);

        $validUser = User::factory()->create(['id' => '100000000000000000']);
        $goneUser = User::factory()->create(['id' => '200000000000000000']);
        $errorUser = User::factory()->create(['id' => '300000000000000000']);

        $this->mock(DiscordGuildService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getGuildMember')
                ->with('100000000000000000')
                ->once()
                ->andReturn([
                    'nick' => 'ValidUser',
                    'avatar' => null,
                    'banner' => null,
                    'roles' => ['111111111111111111'],
                ]);

            $mock->shouldReceive('getGuildMember')
                ->with('200000000000000000')
                ->once()
                ->andThrow(new UserNotInGuildException('User not in guild'));

            $mock->shouldReceive('getGuildMember')
                ->with('300000000000000000')
                ->once()
                ->andThrow(new \RuntimeException('API Error'));
        });

        SyncDiscordUsers::dispatchSync();

        // Valid user was synced
        $validUser->refresh();
        $this->assertSame('ValidUser', $validUser->nickname);
        $this->assertTrue($validUser->discordRoles->contains($role));

        // Gone user was deleted
        $this->assertDatabaseMissing('users', ['id' => '200000000000000000']);

        // Errored user was preserved
        $this->assertDatabaseHas('users', ['id' => '300000000000000000']);
    }

    public function test_it_only_syncs_recognized_role_ids(): void
    {
        DiscordRole::factory()->create([
            'id' => '111111111111111111',
            'name' => 'Officer',
            'position' => 10,
        ]);

        $user = User::factory()->create(['id' => '100000000000000000']);

        $this->mock(DiscordGuildService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getGuildMember')
                ->with('100000000000000000')
                ->once()
                ->andReturn([
                    'nick' => null,
                    'avatar' => null,
                    'banner' => null,
                    'roles' => ['111111111111111111', '999999999999999999'],
                ]);
        });

        SyncDiscordUsers::dispatchSync();

        $user->refresh();
        $this->assertCount(1, $user->discordRoles);
        $this->assertTrue($user->discordRoles->contains('id', '111111111111111111'));
    }
}
