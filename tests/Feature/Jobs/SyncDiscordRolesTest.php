<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncDiscordRoles;
use App\Models\DiscordRole;
use App\Services\Discord\DiscordRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SyncDiscordRolesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sample Discord API role response data.
     *
     * @return array<int, array{id: string, name: string, position: int}>
     */
    private function discordApiRoles(): array
    {
        return [
            ['id' => '000000000000000000', 'name' => '@everyone', 'position' => 0],
            ['id' => '111111111111111111', 'name' => 'Officer', 'position' => 10],
            ['id' => '222222222222222222', 'name' => 'Raider', 'position' => 20],
            ['id' => '333333333333333333', 'name' => 'Member', 'position' => 30],
        ];
    }

    public function test_it_creates_new_roles_from_discord(): void
    {
        $this->mock(DiscordRoleService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getAllRoles')->once()->andReturn($this->discordApiRoles());
        });

        SyncDiscordRoles::dispatchSync();

        $this->assertDatabaseCount('discord_roles', 3);
        $this->assertDatabaseHas('discord_roles', ['id' => '111111111111111111', 'name' => 'Officer', 'position' => 10]);
        $this->assertDatabaseHas('discord_roles', ['id' => '222222222222222222', 'name' => 'Raider', 'position' => 20]);
        $this->assertDatabaseHas('discord_roles', ['id' => '333333333333333333', 'name' => 'Member', 'position' => 30]);
    }

    public function test_it_updates_existing_roles(): void
    {
        DiscordRole::factory()->create([
            'id' => '111111111111111111',
            'name' => 'Old Name',
            'position' => 99,
        ]);

        $this->mock(DiscordRoleService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getAllRoles')->once()->andReturn($this->discordApiRoles());
        });

        SyncDiscordRoles::dispatchSync();

        $this->assertDatabaseHas('discord_roles', [
            'id' => '111111111111111111',
            'name' => 'Officer',
            'position' => 10,
        ]);
    }

    public function test_it_deletes_roles_no_longer_in_discord(): void
    {
        $orphanedRole = DiscordRole::factory()->create([
            'id' => '999999999999999999',
            'name' => 'Deleted Role',
            'position' => 50,
        ]);

        $this->mock(DiscordRoleService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getAllRoles')->once()->andReturn($this->discordApiRoles());
        });

        SyncDiscordRoles::dispatchSync();

        $this->assertDatabaseMissing('discord_roles', ['id' => '999999999999999999']);
    }

    public function test_it_excludes_the_everyone_role(): void
    {
        $this->mock(DiscordRoleService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getAllRoles')->once()->andReturn($this->discordApiRoles());
        });

        SyncDiscordRoles::dispatchSync();

        $this->assertDatabaseMissing('discord_roles', ['id' => '000000000000000000']);
    }

    public function test_it_preserves_is_visible_on_existing_roles(): void
    {
        DiscordRole::factory()->visible()->create([
            'id' => '111111111111111111',
            'name' => 'Officer',
            'position' => 10,
        ]);

        $this->mock(DiscordRoleService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getAllRoles')->once()->andReturn($this->discordApiRoles());
        });

        SyncDiscordRoles::dispatchSync();

        $this->assertDatabaseHas('discord_roles', [
            'id' => '111111111111111111',
            'is_visible' => true,
        ]);
    }
}
