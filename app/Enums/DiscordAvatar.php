<?php

namespace App\Enums;

/**
 * A Discord avatar kind, backed by its Media Library collection name.
 */
enum DiscordAvatar: string
{
    case Guild = 'discord_guild_avatar';
    case User = 'discord_avatar';

    /**
     * Get the name of the user attribute holding this avatar's Discord hash.
     */
    public function hashAttribute(): string
    {
        return match ($this) {
            self::Guild => 'guild_avatar',
            self::User => 'avatar',
        };
    }
}
