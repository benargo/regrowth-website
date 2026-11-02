<?php

namespace App\Models;

use App\Casts\AsBinaryColor;
use App\Events\DiscordRoleDeleted;
use App\Events\DiscordRoleUpdated;
use App\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * This model must NOT use Spatie's `SortableTrait`. The `position` column
 * reflects the role's ordering within the Discord server and is set by
 * Discord, not by this application. Applying `SortableTrait` would let us
 * overwrite `position` with our own values, which would fight Discord for
 * ownership of that column and desync the ordering.
 */
#[Fillable(['id', 'name', 'position', 'primary_color', 'secondary_color', 'tertiary_color', 'is_visible', 'grants_permissions'])]
#[Table(keyType: 'string', incrementing: false)]
class DiscordRole extends Model
{
    use HasFactory, HasPermissions;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_visible' => false,
        'grants_permissions' => false,
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'deleted' => DiscordRoleDeleted::class,
        'updated' => DiscordRoleUpdated::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'position' => 'integer',
            'primary_color' => AsBinaryColor::class,
            'secondary_color' => AsBinaryColor::class,
            'tertiary_color' => AsBinaryColor::class,
            'is_visible' => 'boolean',
            'grants_permissions' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'discord_role_user', 'discord_role_id', 'user_id');
    }

    /**
     * Scope the query to roles that grant permissions.
     *
     * @param  Builder<DiscordRole>  $query
     */
    #[Scope]
    protected function grantingPermissions(Builder $query): void
    {
        $query->where('grants_permissions', true);
    }

    /**
     * Scope the query to roles that are visible on the site.
     *
     * @param  Builder<DiscordRole>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('is_visible', true);
    }
}
