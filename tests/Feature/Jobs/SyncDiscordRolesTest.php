<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncDiscordRoles;
use App\Models\DiscordRole;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\Role;
use App\Services\Discord\Resources\RoleColors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncDiscordRolesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return Collection<Role>
     */
    private function discordApiRoles(): Collection
    {
        $colors = new RoleColors(primary_color: 0);

        return collect([
            new Role(id: '000000000000000000', name: '@everyone', colors: $colors, hoist: false, position: 0, permissions: '0', managed: false, mentionable: false, flags: 0),
            new Role(id: '111111111111111111', name: 'Officer', colors: $colors, hoist: false, position: 10, permissions: '0', managed: false, mentionable: false, flags: 0),
            new Role(id: '222222222222222222', name: 'Raider', colors: $colors, hoist: false, position: 20, permissions: '0', managed: false, mentionable: false, flags: 0),
            new Role(id: '333333333333333333', name: 'Member', colors: $colors, hoist: false, position: 30, permissions: '0', managed: false, mentionable: false, flags: 0),
        ]);
    }

    #[Test]
    public function it_creates_new_roles_from_discord(): void
    {
        $this->mock(Discord::class, function (MockInterface $mock) {
            $mock->shouldReceive('getGuildRoles')->once()->andReturn($this->discordApiRoles());
        });

        SyncDiscordRoles::dispatchSync();

        $this->assertDatabaseCount('discord_roles', 3);
        $this->assertDatabaseHas('discord_roles', ['id' => '111111111111111111', 'name' => 'Officer', 'position' => 10]);
        $this->assertDatabaseHas('discord_roles', ['id' => '222222222222222222', 'name' => 'Raider', 'position' => 20]);
        $this->assertDatabaseHas('discord_roles', ['id' => '333333333333333333', 'name' => 'Member', 'position' => 30]);
    }

    #[Test]
    public function it_updates_existing_roles(): void
    {
        DiscordRole::factory()->create([
            'id' => '111111111111111111',
            'name' => 'Old Name',
            'position' => 99,
        ]);

        $this->mock(Discord::class, function (MockInterface $mock) {
            $mock->shouldReceive('getGuildRoles')->once()->andReturn($this->discordApiRoles());
        });

        SyncDiscordRoles::dispatchSync();

        $this->assertDatabaseHas('discord_roles', [
            'id' => '111111111111111111',
            'name' => 'Officer',
            'position' => 10,
        ]);
    }

    #[Test]
    public function it_deletes_roles_no_longer_in_discord(): void
    {
        $orphanedRole = DiscordRole::factory()->create([
            'id' => '999999999999999999',
            'name' => 'Deleted Role',
            'position' => 50,
        ]);

        $this->mock(Discord::class, function (MockInterface $mock) {
            $mock->shouldReceive('getGuildRoles')->once()->andReturn($this->discordApiRoles());
        });

        SyncDiscordRoles::dispatchSync();

        $this->assertDatabaseMissing('discord_roles', ['id' => '999999999999999999']);
    }

    #[Test]
    public function it_excludes_the_everyone_role(): void
    {
        $this->mock(Discord::class, function (MockInterface $mock) {
            $mock->shouldReceive('getGuildRoles')->once()->andReturn($this->discordApiRoles());
        });

        SyncDiscordRoles::dispatchSync();

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

        $this->mock(Discord::class, function (MockInterface $mock) {
            $mock->shouldReceive('getGuildRoles')->once()->andReturn($this->discordApiRoles());
        });

        SyncDiscordRoles::dispatchSync();

        $this->assertDatabaseHas('discord_roles', [
            'id' => '111111111111111111',
            'is_visible' => true,
        ]);
    }
}
