<?php

namespace App\Enums;

use App\Http\Integrations\Discord\Requests\Cdn\FetchGuildMemberAvatarRequest;
use App\Http\Integrations\Discord\Requests\Cdn\FetchUserAvatarRequest;
use Saloon\Http\Request;

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

    /**
     * Get the Discord CDN request that fetches this avatar for the given user and hash.
     */
    public function cdnRequest(string $userId, string $hash): Request
    {
        return match ($this) {
            self::Guild => new FetchGuildMemberAvatarRequest((string) config('services.discord.server_id'), $userId, $hash),
            self::User => new FetchUserAvatarRequest($userId, $hash),
        };
    }
}
