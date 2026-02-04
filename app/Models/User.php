<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Config;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The primary key type.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'username',
        'discriminator',
        'nickname',
        'avatar',
        'guild_avatar',
        'banner',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'remember_token',
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
        ];
    }

    public function discordRoles(): BelongsToMany
    {
        return $this->belongsToMany(DiscordRole::class, 'discord_role_user', 'user_id', 'discord_role_id');
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
     * Get the user's highest role name.
     */
    public function highestRole(): ?string
    {
        return $this->discordRoles->sortBy('position')->first()?->name;
    }

    /**
     * Determine if the user can comment on loot items
     */
    public function canCommentOnLootItems(): bool
    {
        return $this->discordRoles->where('can_comment_on_loot_items', true)->isNotEmpty();
    }

    /**
     * Get the user's display name (nickname or username).
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->nickname ?? $this->username
        );
    }

    /**
     * Get the user's Discord avatar URL.
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->guild_avatar) {
                    $guild_id = Config::get('services.discord.guild_id');

                    return "https://cdn.discordapp.com/guilds/{$guild_id}/users/{$this->id}/avatars/{$this->guild_avatar}.webp";
                } elseif ($this->avatar) {
                    return "https://cdn.discordapp.com/avatars/{$this->id}/{$this->avatar}.webp";
                }
                // Default Discord avatar based on user ID
                $defaultIndex = ((int) $this->id >> 22) % 6;

                return "https://cdn.discordapp.com/embed/avatars/{$defaultIndex}.png";
            }
        );
    }

    /**
     * Get the user's Discord banner URL.
     */
    protected function bannerUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->banner
                ? "https://cdn.discordapp.com/banners/{$this->id}/{$this->banner}.webp"
                : null
        );
    }
}
