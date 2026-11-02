<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\PermissionResource;
use App\Models\DiscordRole;
use App\Models\Permission;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('auth')]
class PermissionResourceTest extends TestCase
{
    #[Test]
    #[Group('resource')]
    public function it_falls_back_to_the_headlined_key_when_the_label_is_empty(): void
    {
        $permission = Permission::factory()->make(['name' => 'view-old-raid-plans', 'label' => null]);

        $data = (new PermissionResource($permission))->resolve(Request::create('/'));

        $this->assertSame('View Old Raid Plans', $data['label']);
    }

    #[Test]
    #[Group('resource')]
    public function it_lists_the_ids_of_roles_that_hold_it_when_loaded(): void
    {
        $permission = Permission::factory()->make();
        $permission->setRelation('discordRoles', collect([
            new DiscordRole(['id' => '1']),
            new DiscordRole(['id' => '2']),
        ]));

        $data = (new PermissionResource($permission))->resolve(Request::create('/'));

        $this->assertEquals(['1', '2'], $data['discord_role_ids']->all());
    }

    #[Test]
    #[Group('resource')]
    public function it_omits_role_ids_when_not_loaded(): void
    {
        $permission = Permission::factory()->make();

        $data = (new PermissionResource($permission))->resolve(Request::create('/'));

        $this->assertArrayNotHasKey('discord_role_ids', $data);
    }

    #[Test]
    #[Group('resource')]
    public function it_returns_the_role_count_when_counted(): void
    {
        $permission = Permission::factory()->make();
        $permission->setAttribute('discord_roles_count', 2);

        $data = (new PermissionResource($permission))->resolve(Request::create('/'));

        $this->assertSame(2, $data['discord_roles_count']);
    }

    #[Test]
    #[Group('resource')]
    public function it_omits_the_role_count_when_not_counted(): void
    {
        $permission = Permission::factory()->make();

        $data = (new PermissionResource($permission))->resolve(Request::create('/'));

        $this->assertArrayNotHasKey('discord_roles_count', $data);
    }
}
