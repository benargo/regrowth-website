<?php

namespace Tests\Feature\Actions\Discord;

use App\Actions\Discord\SyncUserDiscordRoles;
use App\Models\DiscordRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('discord-integration')]
class SyncUserDiscordRolesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    public function it_attaches_only_recognised_roles(): void
    {
        $user = User::factory()->create();
        $role = DiscordRole::factory()->create();

        SyncUserDiscordRoles::run($user, [$role->id, '999999999999999999']);

        $this->assertSame([$role->id], $user->discordRoles()->pluck('id')->all());
    }

    #[Test]
    #[Group('edge-case')]
    public function it_detaches_roles_the_member_no_longer_has(): void
    {
        $user = User::factory()->create();
        $keptRole = DiscordRole::factory()->create();
        $removedRole = DiscordRole::factory()->create();
        $user->discordRoles()->attach([$keptRole->id, $removedRole->id]);

        SyncUserDiscordRoles::run($user, [$keptRole->id]);

        $this->assertSame([$keptRole->id], $user->discordRoles()->pluck('id')->all());
    }
}
