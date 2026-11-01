<?php

namespace App\Actions\Permissions;

use App\Models\PermissionGroup;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Stores the order of the permission groups: the first id gets sort order 1.
 * Each group is saved on its own, inside a single transaction.
 */
class ReorderPermissionGroups
{
    use AsAction;

    /**
     * @param  list<string>  $permissionGroupIds
     */
    public function handle(array $permissionGroupIds): void
    {
        $groups = PermissionGroup::whereKey($permissionGroupIds)->get()->keyBy('id');

        DB::transaction(function () use ($permissionGroupIds, $groups): void {
            foreach (array_values($permissionGroupIds) as $index => $permissionGroupId) {
                $groups->get($permissionGroupId)?->update(['sort_order' => $index + 1]);
            }
        });
    }
}
