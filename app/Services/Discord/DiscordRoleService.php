<?php

namespace App\Services\Discord;

use App\Services\Discord\Exceptions\RoleNotFoundException;

class DiscordRoleService extends DiscordService
{
    protected string $guildId;

    public function __construct(string $botToken, string $guildId)
    {
        parent::__construct($botToken);
        $this->guildId = $guildId;
    }

    /**
     * Get all roles in the Discord guild.
     *
     * @throws \RuntimeException
     */
    public function getAllRoles(): array
    {
        $response = $this->get("/guilds/{$this->guildId}/roles");

        if ($response->failed()) {
            throw new RuntimeException('Failed to fetch guild roles: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Get role data for a Discord role ID.
     *
     * @throws \RoleNotFoundException
     * @throws \RuntimeException
     */
    public function getRole(string $roleId): array
    {
        $response = $this->get("/guilds/{$this->guildId}/roles/{$roleId}");

        if ($response->failed()) {
            throw new RuntimeException('Failed to fetch guild roles: '.$response->body());
        }

        $role = $response->json();

        throw new RoleNotFoundException("Role {$roleId} not found in guild {$this->guildId}");
    }
}
