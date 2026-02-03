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
}
