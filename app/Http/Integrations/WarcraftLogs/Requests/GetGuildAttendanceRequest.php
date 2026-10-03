<?php

namespace App\Http\Integrations\WarcraftLogs\Requests;

use App\Http\Integrations\WarcraftLogs\Concerns\ThrowsGuildNotFound;
use App\Http\Integrations\WarcraftLogs\Data\Attendance\GuildAttendanceData;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

final class GetGuildAttendanceRequest extends WarcraftLogsRequest implements Paginatable
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
        query GetGuildAttendanceData($id: Int!, $page: Int, $limit: Int) {
            guildData {
                guild(id: $id) {
                    attendance(page: $page, limit: $limit) {
                        data {
                            code
                            startTime
                            players {
                                name
                                presence
                            }
                            zone {
                                id
                                name
                                difficulties {
                                    id
                                    name
                                    sizes
                                }
                                expansion {
                                    id
                                    name
                                }
                            }
                        }
                        current_page
                        has_more_pages
                    }
                }
            }
        }
        GRAPHQL;
    }

    /**
     * @return array{id: int, page: int, limit: int}
     */
    protected function variables(): array
    {
        return [
            'id' => $this->guildId,
            'page' => 1,
            'limit' => 25,
        ];
    }

    public function cacheExpiryInSeconds(): int
    {
        return 43200; // 12 hours
    }

    /**
     * @return array<int, GuildAttendanceData>
     */
    public function createDtoFromResponse(Response $response): array
    {
        $attendance = $response->json('data.guildData.guild.attendance');

        if ($attendance === null) {
            throw $this->guildNotFound($response);
        }

        return GuildAttendanceData::collect($attendance['data'] ?? []);
    }
}
