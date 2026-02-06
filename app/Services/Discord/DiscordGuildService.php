<?php

namespace App\Services\Discord;

use App\Services\Discord\Exceptions\UserNotInGuildException;

class DiscordGuildService extends DiscordService
{
    protected string $guildId;

    public function __construct(string $botToken, string $guildId)
    {
        parent::__construct($botToken);
        $this->guildId = $guildId;
    }

    /**
     * Get guild member data for a Discord user.
     *
     * @throws UserNotInGuildException
     * @throws \RuntimeException
     */
    public function getGuildMember(string $userId): array
    {
        $response = $this->get("/guilds/{$this->guildId}/members/{$userId}");

        if ($response->status() === 404) {
            throw new UserNotInGuildException("User {$userId} is not a member of guild {$this->guildId}");
        }

        if ($response->failed()) {
            throw new \RuntimeException('Failed to fetch guild member data: '.$response->body());
        }

        $data = $response->json();

        return [
            'nick' => $data['nick'] ?? null,
            'avatar' => $data['avatar'] ?? null,
            'banner' => $data['banner'] ?? null,
            'roles' => $data['roles'] ?? [],
        ];
    }

    /**
     * Search for guild members whose username or nickname starts with the provided string.
     *
     * @param  string  $query  Query string to match username(s) and nickname(s) against.
     * @param  int  $limit  Max number of members to return (1-1000). Defaults to 1.
     * @return array<int, array{
     *     user: array,
     *     nick: string|null,
     *     avatar: string|null,
     *     roles: array,
     *     joined_at: string,
     *     deaf: bool,
     *     mute: bool
     * }>
     *
     * @throws \RuntimeException
     */
    public function searchGuildMembers(string $query, int $limit = 1): array
    {
        $response = $this->get("/guilds/{$this->guildId}/members/search", [
            'query' => $query,
            'limit' => min(max($limit, 1), 1000),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to search guild members: '.$response->body());
        }

        return $response->json();
    }
}
