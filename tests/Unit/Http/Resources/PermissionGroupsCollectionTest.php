<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\PermissionGroupsCollection;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PermissionGroupsCollectionTest extends TestCase
{
    use RefreshDatabase;

    // ==================== toArray ====================

    #[Test]
    public function it_wraps_permissions_into_a_collection(): void
    {
        $permissions = collect([
            Permission::firstOrCreate(['name' => 'perm-one', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'perm-two', 'guard_name' => 'web']),
        ]);

        $array = (new PermissionGroupsCollection($permissions))->toArray(new Request);

        $this->assertCount(2, $array);
    }

    #[Test]
    public function it_returns_empty_array_for_empty_collection(): void
    {
        $array = (new PermissionGroupsCollection(collect()))->toArray(new Request);

        $this->assertCount(0, $array);
    }
}
