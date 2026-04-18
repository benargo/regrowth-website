<?php

namespace App\Services\WarcraftLogs;

use App\Services\WarcraftLogs\Exceptions\GraphQLException;
use App\Services\WarcraftLogs\Exceptions\GuildNotFoundException;
use App\Services\WarcraftLogs\Traits\Paginates;
use App\Services\WarcraftLogs\ValueObjects\GuildAttendance;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class Attendance extends BaseService
{
    use Paginates;

    protected int $cacheTtl = 43200; // 12 hours

    /**
     * The attendance data to calculate stats from.
     */
    protected ?iterable $attendance = null;

    /**
     * Optional player names filter.
     *
     * @var array<string>|null
     */
    protected ?array $playerNames = null;

    /**
     * Optional zone ID filter.
     */
    protected ?int $zoneID = null;

    /**
     * Set the attendance data to work with.
     */
    public function setAttendance(iterable $attendance): static
    {
        $this->attendance = $attendance;

        return $this;
    }

    /**
     * Set the player names filter.
     *
     * @param  array<string>|null  $playerNames
     */
    public function playerNames(?array $playerNames): static
    {
        $this->playerNames = $playerNames;

        return $this;
    }

    /**
     * Set the zone ID filter.
     */
    public function zoneID(?int $zoneID): static
    {
        $this->zoneID = $zoneID;

        return $this;
    }

    /**
     * Fetch all attendance data from the API, deduplicated and sorted by date ascending.
     *
     * If attendance has been pre-loaded via setAttendance(), that data is returned instead.
     *
     * @return Collection<int, GuildAttendance>
     */
    public function get(): Collection
    {
        if ($this->attendance !== null) {
            return $this->sortAttendanceData($this->attendance);
        }

        return $this->paginateAll(
            fn (int $page) => $this->fetchAttendancePage($page, $this->playerNames, $this->zoneID),
        )
            ->sortBy(fn (GuildAttendance $a) => $a->startTime)
            ->values();
    }

    /**
     * Fetch attendance data lazily for memory efficiency.
     *
     * If attendance has been pre-loaded via setAttendance(), that data is returned instead.
     *
     * @return LazyCollection<int, GuildAttendance>
     */
    public function lazy(): LazyCollection
    {
        if ($this->attendance !== null) {
            return LazyCollection::make($this->attendance);
        }

        return $this->paginateLazy(
            fn (int $page) => $this->fetchAttendancePage($page, $this->playerNames, $this->zoneID),
        );
    }

    /**
     * Get the attendance records sorted by date ascending.
     *
     * @param  iterable<GuildAttendance>  $attendance
     * @return Collection<int, GuildAttendance>
     */
    protected function sortAttendanceData(iterable $attendance): Collection
    {
        return collect($attendance)->sortBy(fn (GuildAttendance $a) => $a->startTime)->values();
    }

    /**
     * Fetch a single page of attendance and return a normalized result for pagination.
     *
     * @param  array<string>|null  $playerNames  Filter to only include these players.
     * @return array{items: array<GuildAttendance>, hasMorePages: bool}
     *
     * @throws GuildNotFoundException
     * @throws GraphQLException
     */
    protected function fetchAttendancePage(
        int $page,
        ?array $playerNames,
        ?int $zoneID,
        ?int $guildId = null,
        ?int $limit = null,
    ): array {
        $result = $this->querySingleTagAttendance(
            $guildId ?? $this->guildId,
            $page,
            $limit ?? 25,
            $zoneID,
        );

        $items = [];

        foreach ($result['data'] as $attendance) {
            if ($playerNames !== null) {
                $attendance = $attendance->filterPlayers($playerNames);
                if (empty($attendance->players)) {
                    continue;
                }
            }

            $items[] = $attendance;
        }

        return ['items' => $items, 'hasMorePages' => $result['hasMorePages']];
    }

    /**
     * Fetch a single page of attendance for a guild.
     *
     * @return array{data: array<GuildAttendance>, hasMorePages: bool}
     *
     * @throws GuildNotFoundException
     * @throws GraphQLException
     */
    protected function querySingleTagAttendance(
        int $guildId,
        ?int $page = 1,
        ?int $limit = 25,
        ?int $zoneID = null,
    ): array {
        $query = $this->buildGraphQuery($zoneID);

        $variables = [
            'id' => $guildId,
            'page' => $page,
            'limit' => $limit,
        ];

        if ($zoneID !== null) {
            $variables['zoneID'] = $zoneID;
        }

        try {
            $data = $this->query($query, $variables);
        } catch (GraphQLException $e) {
            if ($e->hasErrorMatching('/does not exist/i') || $e->hasErrorMatching('/not found/i')) {
                throw new GuildNotFoundException("Guild with ID {$guildId} not found");
            }
            throw $e;
        }

        $attendanceData = $data['guildData']['guild']['attendance'] ?? null;

        if ($attendanceData === null) {
            throw new GuildNotFoundException("Guild with ID {$guildId} not found");
        }

        return [
            'data' => Arr::map(
                $attendanceData['data'] ?? [],
                fn (array $attendance) => GuildAttendance::fromArray($attendance),
            ),
            'hasMorePages' => $attendanceData['has_more_pages'] ?? false,
        ];
    }

    /**
     * Build the GraphQL query for fetching guild attendance data.
     */
    protected function buildGraphQuery(?int $zoneID = null): string
    {
        $variableDefinitions = ['$id: Int!', '$page: Int', '$limit: Int'];
        $attendanceArgs = ['page: $page', 'limit: $limit'];

        if ($zoneID !== null) {
            $variableDefinitions[] = '$zoneID: Int';
            $attendanceArgs[] = 'zoneID: $zoneID';
        }

        $variableDefinitionsStr = implode(', ', $variableDefinitions);
        $attendanceArgsStr = implode(', ', $attendanceArgs);

        return <<<GRAPHQL
        query GetGuildAttendance({$variableDefinitionsStr}) {
            guildData {
                guild(id: \$id) {
                    attendance({$attendanceArgsStr}) {
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
                        total
                        per_page
                        current_page
                        from
                        to
                        last_page
                        has_more_pages
                    }
                }
            }
        }
        GRAPHQL;
    }
}
