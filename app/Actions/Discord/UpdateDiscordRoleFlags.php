<?php

namespace App\Actions\Discord;

use App\Models\DiscordRole;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Updates a Discord role's permission-granting and visibility flags, detaching its permissions when it stops granting them.
 */
class UpdateDiscordRoleFlags
{
    use AsAction;

    /**
     * @param  array{grants_permissions?: bool, is_visible?: bool}  $flags
     */
    public function handle(DiscordRole $role, array $flags): DiscordRole
    {
        return DB::transaction(function () use ($role, $flags): DiscordRole {
            $role->update($flags);

            if ($role->wasChanged('grants_permissions') && ! $role->grants_permissions) {
                $role->permissions()->detach();
            }

            return $role;
        });
    }
}
