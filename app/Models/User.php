<?php

namespace App\Models;

use App\Enums\DiscordRole;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'roles',
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
            'roles' => 'array',
        ];
    }

    /**
     * Check if user has Officer role.
     */
    public function isOfficer(): bool
    {
        return in_array((string) DiscordRole::Officer->value, $this->roles ?? []);
    }

    /**
     * Check if user has Raider role.
     */
    public function isRaider(): bool
    {
        return in_array((string) DiscordRole::Raider->value, $this->roles ?? []);
    }

    /**
     * Check if user has Member role.
     */
    public function isMember(): bool
    {
        return in_array((string) DiscordRole::Member->value, $this->roles ?? []);
    }

    /**
     * Check if user has Guest role.
     */
    public function isGuest(): bool
    {
        return in_array((string) DiscordRole::Guest->value, $this->roles ?? []);
    }

    /**
     * Get the user's highest role name.
     */
    public function highestRole(): ?string
    {
        foreach (DiscordRole::getRoleHierarchy() as $roleId => $roleName) {
            if (in_array((string) $roleId, $this->roles ?? [])) {
                return $roleName;
            }
        }

        return null;
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
