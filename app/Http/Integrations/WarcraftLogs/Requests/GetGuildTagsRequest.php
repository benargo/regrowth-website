<?php

namespace App\Http\Integrations\WarcraftLogs\Requests;

use App\Http\Integrations\WarcraftLogs\Concerns\ThrowsGuildNotFound;
use App\Http\Integrations\WarcraftLogs\Data\GuildTags\GuildTagData;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use Saloon\Http\Response;

final class GetGuildTagsRequest extends WarcraftLogsRequest
{
    use ThrowsGuildNotFound;

    public function __construct(
        protected readonly int $guildId,
        WarcraftLogsNamespace $namespace,
    ) {
        parent::__construct($namespace);
    }

    protected function graphQLQuery(): string
    {
        return <<<'GRAPHQL'
        query GetGuildTags($id: Int!) {
            guildData {
                guild(id: $id) {
                    id
                    tags {
                        id
                        name
                    }
                }
            }
        }
        GRAPHQL;
    }

    /**
     * @return array{id: int}
     */
    protected function variables(): array
    {
        return ['id' => $this->guildId];
    }

    public function cacheExpiryInSeconds(): int
    {
        return 43200; // 12 hours
    }

    /**
     * @return array<int, GuildTagData>
     */
    public function createDtoFromResponse(Response $response): array
    {
        $guild = $response->json('data.guildData.guild');

        if ($guild === null) {
            throw $this->guildNotFound($response);
        }

        return GuildTagData::collect($guild['tags'] ?? []);
    }
}
