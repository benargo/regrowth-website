<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['id', 'username', 'discriminator', 'nickname', 'avatar', 'guild_avatar', 'banner'])]
#[Hidden(['username', 'discriminator', 'remember_token', 'is_admin', 'created_at', 'updated_at'])]
#[Table(keyType: 'string', incrementing: false)]
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_admin' => false,
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
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Get the user's display name (nickname or username).
     *
     * @return Attribute<string>
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->nickname ?? $this->username,
        );
    }

    /**
     * Get the user's Discord avatar URL: the server avatar, then the user avatar, then Discord's default avatar for the user ID.
     *
     * @return Attribute<string>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if ($this->guild_avatar) {
                    $serverId = config('services.discord.server_id');

                    return "https://cdn.discordapp.com/guilds/{$serverId}/users/{$this->id}/avatars/{$this->guild_avatar}.webp";
                }

                if ($this->avatar) {
                    return "https://cdn.discordapp.com/avatars/{$this->id}/{$this->avatar}.webp";
                }

                $defaultIndex = ((int) $this->id >> 22) % 6;

                return "https://cdn.discordapp.com/embed/avatars/{$defaultIndex}.png";
            },
        );
    }

    /**
     * Get the user's Discord banner URL.
     *
     * @return Attribute<string|null>
     */
    protected function bannerUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->banner
                ? "https://cdn.discordapp.com/banners/{$this->id}/{$this->banner}.webp"
                : null,
        );
    }

    /**
     * Get the characters the user owns.
     *
     * @return HasMany<Character, $this>
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /**
     * Get the Discord roles the user holds.
     *
     * @return BelongsToMany<DiscordRole, $this>
     */
    public function discordRoles(): BelongsToMany
    {
        return $this->belongsToMany(DiscordRole::class, 'discord_role_user', 'user_id', 'discord_role_id');
    }

    /**
     * Get the events the user is associated with.
     *
     * @return BelongsToMany<Event, $this>
     */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'pivot_events_users', 'user_id', 'event_id')
            ->withTimestamps();
    }

    /**
     * Get the notifications associated with the user.
     *
     * @return HasMany<DiscordNotification, $this>
     */
    public function notificationsCreated(): HasMany
    {
        return $this->hasMany(DiscordNotification::class, 'created_by_user_id');
    }

    /**
     * Get the planned absences associated with the user.
     *
     * @return HasMany<PlannedAbsence, $this>
     */
    public function plannedAbsences(): HasMany
    {
        return $this->hasMany(PlannedAbsence::class);
    }

    /**
     * Get the planned absences created by the user.
     *
     * @return HasMany<PlannedAbsence, $this>
     */
    public function plannedAbsencesCreated(): HasMany
    {
        return $this->hasMany(PlannedAbsence::class, 'created_by');
    }

    /**
     * Check if user has Officer role.
     */
    public function isOfficer(): bool
    {
        return $this->discordRoles->contains('name', 'Officer');
    }

    /**
     * Check if user has Raider role.
     */
    public function isRaider(): bool
    {
        return $this->discordRoles->contains('name', 'Raider');
    }

    /**
     * Check if user has Member role.
     */
    public function isMember(): bool
    {
        return $this->discordRoles->contains('name', 'Member');
    }

    /**
     * Check if user has the Loot Councillor role.
     */
    public function isLootCouncillor(): bool
    {
        return $this->discordRoles->contains('name', 'Loot Councillor');
    }

    /**
     * Check if user has Guest role.
     */
    public function isGuest(): bool
    {
        return $this->discordRoles->contains('name', 'Guest');
    }

    /**
     * Get the user's highest visible role.
     */
    public function highestRole(): ?DiscordRole
    {
        return $this->discordRoles->where('is_visible', true)->sortByDesc('position')->first();
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    /**
     * Get a query builder for all permissions the user has access to via their highest Discord role.
     *
     * @return Builder<Permission>
     */
    public function permissions(): Builder
    {
        $highestRoleId = $this->highestRole()?->id;

        return Permission::join('discord_role_has_permissions', 'discord_role_has_permissions.permission_id', '=', 'permissions.id')
            ->where('discord_role_has_permissions.discord_role_id', $highestRoleId)
            ->select('permissions.*');
    }

    /**
     * Check if the user has a permission via any of their Discord roles. Admins have every permission.
     */
    public function isAuthorizedTo(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->discordRoles->contains(fn (DiscordRole $role): bool => $role->hasPermissionTo($permission));
    }
}
