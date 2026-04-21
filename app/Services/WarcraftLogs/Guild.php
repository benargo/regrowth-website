<?php

namespace App\Services\WarcraftLogs;

use App\Services\WarcraftLogs\Exceptions\GuildNotFoundException;
use App\Services\WarcraftLogs\ValueObjects\FactionData;
use App\Services\WarcraftLogs\ValueObjects\ServerData;

class Guild extends BaseService
{
    private int $cacheTtl = 43200; // 12 hours

    /**
     * The Guild data retrieved from Warcraft Logs. This is not to be queried or processed directly.
     */
    private int $id;

    private string $name;

    private ServerData $server;

    private FactionData $faction;

    public function __construct(array $config, AuthenticationHandler $auth)
    {
        parent::__construct($config, $auth);

        $response = $this->query(
            $this->buildGraphQuery(),
            ['id' => $this->guildId]
        );

        $guildData = $response['guildData']['guild'] ?? null;

        if ($guildData === null) {
            throw new GuildNotFoundException("Guild with ID {$this->guildId} not found");
        }

        $this->id = $guildData['id'];
        $this->name = $guildData['name'];
        $this->server = ServerData::from($guildData['server']);
        $this->faction = FactionData::from($guildData['faction']);
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'id' => $this->id,
            'name' => $this->name,
            'server' => $this->server,
            'faction' => $this->faction,
            default => throw new \Exception("Property {$name} does not exist"),
        };
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'server' => $this->server->toArray(),
            'faction' => $this->faction->toArray(),
        ];
    }

    protected function buildGraphQuery(): string
    {
        return <<<'GRAPHQL'
        query GetGuild($id: Int!) {
            guildData {
                guild(id: $id) {
                    id
                    name
                    faction {
                        id
                        name
                    }
                    server {
                        id
                        name
                        slug
                        region {
                            id
                            name
                            slug
                        }
                    }
                }
            }
        }
        GRAPHQL;
    }
}
