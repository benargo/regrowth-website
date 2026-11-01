<?php

namespace App\Actions\Permissions;

use App\Events\PermissionUpdated;
use App\Models\DiscordRole;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Grants or revokes several permissions on one Discord role in a single
 * pivot write, then dispatches PermissionUpdated once for each permission
 * whose state changed.
 */
class SetDiscordRolePermissions
{
    use AsAction;

    /**
     * @param  list<int>  $permissionIds
     */
    public function handle(DiscordRole $role, array $permissionIds, bool $isEnabled): void
    {
        DB::transaction(function () use ($role, $permissionIds, $isEnabled): void {
            $heldIds = $role->permissions()->whereIn('permissions.id', $permissionIds)->pluck('permissions.id');

            $changedIds = $isEnabled
                ? collect($permissionIds)->diff($heldIds)->values()
                : $heldIds->values();

            if ($isEnabled) {
                $role->permissions()->syncWithoutDetaching($permissionIds);
            } else {
                $role->permissions()->detach($permissionIds);
            }

            Permission::whereKey($changedIds)->get()->each(
                fn (Permission $permission) => PermissionUpdated::dispatch($permission)
            );
        });
    }
}
