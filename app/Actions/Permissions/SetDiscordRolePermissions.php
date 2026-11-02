<?php

namespace App\Actions\Permissions;

use App\Events\DiscordRoleUpdated;
use App\Models\DiscordRole;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Grants or revokes several permissions on one Discord role in a single
 * pivot write, then dispatches DiscordRoleUpdated once when any permission's
 * state changed.
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

            if ($changedIds->isNotEmpty()) {
                DiscordRoleUpdated::dispatch($role);
            }
        });
    }
}
