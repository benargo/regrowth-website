<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Permission;

trait HasPermissions
{
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'discord_role_has_permissions',
            'discord_role_id',
            'permission_id'
        );
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

        return $this;
    }

    public function revokePermissionTo(string|Permission $permission): self
    {
        if (is_string($permission)) {
            $permission = Permission::findByName($permission);
        }

        $this->permissions()->detach($permission->id);

        return $this;
    }
}
