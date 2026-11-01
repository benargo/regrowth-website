<?php

namespace App\Models;

use App\Events\PermissionUpdated;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Permission as SpatiePermission;

#[Fillable(['name', 'guard_name', 'label', 'description', 'permission_group_id'])]
#[Hidden(['guard_name', 'created_at', 'updated_at'])]
class Permission extends SpatiePermission
{
    use HasFactory;

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'updated' => PermissionUpdated::class,
    ];

    /**
     * Get the Discord roles associated with this permission.
     *
     * @return BelongsToMany<DiscordRole>
     */
    public function discordRoles(): BelongsToMany
    {
        return $this->belongsToMany(DiscordRole::class, 'discord_role_has_permissions');
    }

    /**
     * Get the group this permission belongs to.
     *
     * @return BelongsTo<PermissionGroup, $this>
     */
    public function permissionGroup(): BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class);
    }
}
