<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\PermissionGroupResource;
use App\Models\Permission;
use App\Models\PermissionGroup;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('auth')]
class PermissionGroupResourceTest extends TestCase
{
    #[Test]
    #[Group('resource')]
    public function it_nests_its_permissions_when_loaded(): void
    {
        $group = PermissionGroup::factory()->make();
        $group->setRelation('permissions', collect([Permission::factory()->make(['name' => 'view-old-raid-plans'])]));

        $data = (new PermissionGroupResource($group))->resolve(Request::create('/'));

        $nested = $data['permissions']->resolve(Request::create('/'));
        $this->assertSame('view-old-raid-plans', $nested[0]['name']);
    }

    #[Test]
    #[Group('resource')]
    public function it_omits_permissions_when_not_loaded(): void
    {
        $group = PermissionGroup::factory()->make();

        $data = (new PermissionGroupResource($group))->resolve(Request::create('/'));

        $this->assertArrayNotHasKey('permissions', $data);
    }
}
