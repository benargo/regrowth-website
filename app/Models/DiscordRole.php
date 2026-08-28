<?php

namespace App\Models;

use App\Events\DiscordRoleUpdated;
use App\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
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
#[Fillable(['id', 'name', 'position', 'is_visible'])]
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
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
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
            'is_visible' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'discord_role_user', 'discord_role_id', 'user_id');
    }
}
