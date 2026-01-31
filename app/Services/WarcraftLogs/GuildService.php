<?php

namespace App\Services\WarcraftLogs;

use App\Services\WarcraftLogs\Data\Guild;
use App\Services\WarcraftLogs\Exceptions\GraphQLException;
use App\Services\WarcraftLogs\Exceptions\GuildNotFoundException;

class GuildService extends WarcraftLogsService
{
    /**
     * Fetch the Regrowth guild using the configured guild ID.
     *
     * @throws GuildNotFoundException
     * @throws GraphQLException
     */
    public function getGuild(): Guild
    {
        return $this->findGuild($this->getGuildId());
    }

    /**
     * Fetch a guild by its ID.
     *
     * @throws GuildNotFoundException
     * @throws GraphQLException
     */
    public function findGuild(int $guildId): Guild
    {
        $query = $this->buildGuildQuery();

        try {
            $data = $this->queryData($query, ['id' => $guildId]);
        } catch (GraphQLException $e) {
            if ($e->hasErrorMatching('/does not exist/i') || $e->hasErrorMatching('/not found/i')) {
                throw new GuildNotFoundException("Guild with ID {$guildId} not found");
            }
            throw $e;
        }

        $guildData = $data['guildData']['guild'] ?? null;

        if ($guildData === null) {
            throw new GuildNotFoundException("Guild with ID {$guildId} not found");
        }

        return Guild::fromArray($guildData);
    }

    /**
     * Fetch a guild by its ID without caching (fresh data).
     *
     * @throws GuildNotFoundException
     * @throws GraphQLException
     */
    public function findGuildFresh(int $guildId): Guild
    {
        $query = $this->buildGuildQuery();

        try {
            $data = $this->queryDataFresh($query, ['id' => $guildId]);
        } catch (GraphQLException $e) {
            if ($e->hasErrorMatching('/does not exist/i') || $e->hasErrorMatching('/not found/i')) {
                throw new GuildNotFoundException("Guild with ID {$guildId} not found");
            }
            throw $e;
        }

        $guildData = $data['guildData']['guild'] ?? null;

        if ($guildData === null) {
            throw new GuildNotFoundException("Guild with ID {$guildId} not found");
        }

        return Guild::fromArray($guildData);
    }

    /**
     * Build the GraphQL query for fetching guild data.
     */
    protected function buildGuildQuery(): string
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
                    tags {
                        id
                        name
                    }
                }
            }
        }
        GRAPHQL;
    }
}
