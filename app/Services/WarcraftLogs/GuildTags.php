<?php

namespace App\Services\WarcraftLogs;

use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Support\Arr;

class GuildTags extends BaseService
{
    private $cacheTtl = 43200; // 12 hours

    private array $tags = [];

    public function __construct(array $config, AuthenticationHandler $auth)
    {
        parent::__construct($config, $auth);

        $response = $this->query(
            $this->buildGraphQuery(),
            ['id' => $this->guildId]
        );

        $tagsData = $response['guildData']['guild']['tags'] ?? [];

        foreach ($tagsData as $tagData) {
            Arr::set(
                $this->tags,
                $tagData['id'],
                GuildTag::updateOrCreate(['id' => $tagData['id']], ['name' => $tagData['name']])
            );
        }
    }

    public function find(int $id): ?GuildTag
    {
        return $this->tags[$id] ?? null;
    }

    public function toArray(): array
    {
        return $this->tags;
    }

    public function toCollection(): \Illuminate\Support\Collection
    {
        return collect($this->tags);
    }

    /**
     * Normalize guild tag ID(s) to an array.
     *
     * @param  int|array<int>|null  $guildTagID
     * @return array<int>
     */
    public static function normalizeGuildTagIDs(int|array|null $guildTagID): array
    {
        if ($guildTagID === null) {
            return [];
        }

        return is_array($guildTagID) ? $guildTagID : [$guildTagID];
    }

    private function buildGraphQuery(): string
    {
        return <<<'GRAPHQL'
        query GetGuild($id: Int!) {
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
}
