<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\PermissionGroupsResource;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

class PermissionGroupsResourceTest extends TestCase
{
    use RefreshDatabase;

    // ==================== collects ====================

    #[Test]
    public function it_collects_permission_model(): void
    {
        $resource = new PermissionGroupsResource(null);

        $this->assertSame(SpatiePermission::class, $resource->collects);
    }

    // ==================== toArray ====================

    #[Test]
    public function it_transforms_permission_to_array(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'test-permission', 'guard_name' => 'web']
        );

        $array = (new PermissionGroupsResource($permission))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertSame('test-permission', $array['name']);
    }
}
