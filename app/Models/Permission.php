<?php

namespace App\Models;

use App\Events\PermissionUpdated;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'guard_name',
        'group',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'guard_name',
        'created_at',
        'updated_at',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'updated' => PermissionUpdated::class,
    ];

    /**
     * Get the group attribute, ensuring it's stored in snake_case.
     */
    protected function group(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value !== null ? Str::slug($value) : null
        );
    }

    /**
     * Get the Discord roles associated with this permission.
     *
     * @return BelongsToMany<DiscordRole>
     */
    public function discordRoles(): BelongsToMany
    {
        return $this->belongsToMany(DiscordRole::class, 'discord_role_has_permissions');
    }
}
