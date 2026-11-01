<?php

namespace Tests\Feature\Actions\Permissions;

use App\Actions\Permissions\ReorderPermissionGroups;
use App\Models\PermissionGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[Group('auth')]
class ReorderPermissionGroupsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_the_given_order(): void
    {
        [$first, $second, $third] = PermissionGroup::factory()->count(3)->create();

        ReorderPermissionGroups::run([$third->id, $second->id, $first->id]);

        $this->assertSame(1, $third->fresh()->sort_order);
        $this->assertSame(2, $second->fresh()->sort_order);
        $this->assertSame(3, $first->fresh()->sort_order);
    }

    #[Test]
    public function it_rolls_back_when_a_save_fails(): void
    {
        [$first, $second, $third] = PermissionGroup::factory()->count(3)->create();
        $original = [$first->sort_order, $second->sort_order, $third->sort_order];

        PermissionGroup::updating(function (PermissionGroup $group) use ($third): void {
            if ($group->is($third)) {
                throw new RuntimeException('Save failed.');
            }
        });

        try {
            ReorderPermissionGroups::run([$second->id, $third->id, $first->id]);
            $this->fail('Expected the reorder to throw.');
        } catch (RuntimeException) {
            // Expected.
        }

        $this->assertSame($original, [
            $first->fresh()->sort_order,
            $second->fresh()->sort_order,
            $third->fresh()->sort_order,
        ]);
    }
}
