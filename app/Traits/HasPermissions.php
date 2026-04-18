<?php

namespace App\Traits;

use App\Events\PermissionUpdated;
use App\Models\Permission;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasPermissions
{
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'discord_role_has_permissions');
    }

    public function hasPermissionTo(string $permission): bool
    {
        return $this->permissions->contains('name', $permission);
    }

    public function givePermissionTo(string|Permission $permission): self
    {
        if (is_string($permission)) {
            $permission = Permission::findByName($permission);
        }

        $this->permissions()->syncWithoutDetaching([$permission->id]);
        PermissionUpdated::dispatch($permission);

        return $this;
    }

    public function revokePermissionTo(string|Permission $permission): self
    {
        if (is_string($permission)) {
            $permission = Permission::findByName($permission);
        }

        $this->permissions()->detach($permission->id);
        PermissionUpdated::dispatch($permission);

        return $this;
    }
}
