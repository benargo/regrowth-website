<?php

namespace App\Services\WarcraftLogs;

use App\Services\WarcraftLogs\Data\GuildAttendance;
use App\Services\WarcraftLogs\Data\GuildAttendancePagination;
use App\Services\WarcraftLogs\Exceptions\GraphQLException;
use App\Services\WarcraftLogs\Exceptions\GuildNotFoundException;
use App\Services\WarcraftLogs\Traits\Paginates;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class Attendance extends BaseService
{
    use Paginates;

    protected int $cacheTtl = 43200; // 12 hours

    /**
     * The attendance data to calculate stats from.
     */
    protected iterable $attendance = [];

    /**
     * The guild tags to filter by when fetching attendance.
     *
     * @var array<int>
     */
    protected array $tags = [];

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
     * Set the guild tags to filter by.
     *
     * @param  array<int>  $tags
     */
    public function tags(array $tags): static
    {
        $this->tags = $tags;

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
     * Fetch attendance data for all configured tags and merge the results.
     *
     * If no tags are configured, returns the set attendance data.
     * Results are deduplicated by report code and sorted by date ascending.
     *
     * @return Collection<int, GuildAttendance>
     */
    public function get(): Collection
    {
        if (empty($this->tags)) {
            return $this->sortAttendanceData($this->attendance);
        }

        $allRecords = $this->paginateAllAcrossTags(
            $this->tags,
            fn (int $tagID) => fn (int $page) => $this->fetchAttendancePage(
                $page, $tagID, $this->playerNames, $this->zoneID,
            ),
        );

        return $allRecords
            ->sortBy(fn (GuildAttendance $a) => $a->startTime)
            ->values();
    }

    /**
     * Fetch attendance data lazily for memory efficiency.
     *
     * If no tags are configured, returns the set attendance data.
     * Note: When using multiple tags, deduplication is still performed but
     * results are yielded as they are processed.
     *
     * @return LazyCollection<int, GuildAttendance>
     */
    public function lazy(): LazyCollection
    {
        if (empty($this->tags)) {
            return LazyCollection::make($this->attendance);
        }

        return $this->paginateLazyAcrossTags(
            $this->tags,
            fn (int $tagID) => $this->getSingleTagAttendanceLazy(
                $this->guildId,
                25,
                $this->playerNames,
                $tagID,
                $this->zoneID,
            ),
        );
    }

    /**
     * Get the first attendance date for a specific player.
     */
    public function getPlayerFirstAttendanceDate(string $playerName): ?Carbon
    {
        foreach ($this->attendance as $record) {
            foreach ($record->players as $player) {
                if ($player->name === $playerName) {
                    return $record->startTime;
                }
            }
        }

        return null;
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

    // ==================== API Query Methods ====================

    /**
     * Fetch a single page of attendance for the configured guild.
     *
     * @param  array{page?: int, limit?: int, playerNames?: array<string>, guildTagID?: int|array<int>, zoneID?: int}  $params
     *
     * @throws GuildNotFoundException
     * @throws GraphQLException
     */
    public function getAttendance(array $params = []): GuildAttendancePagination
    {
        return $this->getGuildAttendance(
            $this->guildId,
            $params['page'] ?? 1,
            $params['limit'] ?? 25,
            $params['playerNames'] ?? null,
            $params['guildTagID'] ?? null,
            $params['zoneID'] ?? null,
        );
    }

    /**
     * Fetch a single page of attendance for a guild.
     *
     * @param  array<string>|null  $playerNames  Filter to only include these players.
     * @param  int|array<int>|null  $guildTagID  Single tag ID or array of tag IDs.
     *
     * @throws GuildNotFoundException
     * @throws GraphQLException
     */
    public function getGuildAttendance(
        int $guildId,
        ?int $page = 1,
        ?int $limit = 25,
        ?array $playerNames = null,
        int|array|null $guildTagID = null,
        ?int $zoneID = null,
    ): GuildAttendancePagination {
        $tagIDs = GuildTags::normalizeGuildTagIDs($guildTagID);

        // Single tag or no tag: use existing logic
        if (count($tagIDs) <= 1) {
            return $this->querySingleTagAttendance(
                $guildId,
                $page,
                $limit,
                $playerNames,
                $tagIDs[0] ?? null,
                $zoneID,
            );
        }

        // Multiple tags: query each and merge
        return $this->queryMultiTagAttendance(
            $guildId,
            $page,
            $limit,
            $playerNames,
            $tagIDs,
            $zoneID,
        );
    }

    /**
     * Fetch a single page of attendance for a single guild tag.
     *
     * @param  array<string>|null  $playerNames  Filter to only include these players.
     *
     * @throws GuildNotFoundException
     * @throws GraphQLException
     */
    protected function querySingleTagAttendance(
        int $guildId,
        ?int $page = 1,
        ?int $limit = 25,
        ?array $playerNames = null,
        ?int $guildTagID = null,
        ?int $zoneID = null,
    ): GuildAttendancePagination {
        $query = $this->buildGraphQuery($guildTagID, $zoneID);

        $variables = [
            'id' => $guildId,
            'page' => $page,
            'limit' => $limit,
        ];

        if ($guildTagID !== null) {
            $variables['guildTagID'] = $guildTagID;
        }

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

        $pagination = GuildAttendancePagination::fromArray($attendanceData);

        if ($playerNames !== null) {
            $filteredData = array_map(
                fn (GuildAttendance $attendance) => $attendance->filterPlayers($playerNames),
                $pagination->data,
            );
            $filteredData = array_filter(
                $filteredData,
                fn (GuildAttendance $attendance) => ! empty($attendance->players),
            );

            return new GuildAttendancePagination(
                data: array_values($filteredData),
                total: $pagination->total,
                perPage: $pagination->perPage,
                currentPage: $pagination->currentPage,
                from: $pagination->from,
                to: $pagination->to,
                lastPage: $pagination->lastPage,
                hasMorePages: $pagination->hasMorePages,
            );
        }

        return $pagination;
    }

    /**
     * Query attendance for multiple guild tags and merge results.
     *
     * @param  array<string>|null  $playerNames  Filter to only include these players.
     * @param  array<int>  $guildTagIDs
     *
     * @throws GuildNotFoundException
     * @throws GraphQLException
     */
    protected function queryMultiTagAttendance(
        int $guildId,
        ?int $page,
        ?int $limit,
        ?array $playerNames,
        array $guildTagIDs,
        ?int $zoneID,
    ): GuildAttendancePagination {
        $allRecords = $this->paginateAllAcrossTags(
            $guildTagIDs,
            fn (int $tagID) => fn (int $fetchPage) => $this->fetchAttendancePage(
                $fetchPage, $tagID, $playerNames, $zoneID, $guildId, 100,
            ),
        );

        // Sort by startTime descending
        $sorted = $allRecords->sortByDesc(fn (GuildAttendance $a) => $a->startTime)->values();

        // Apply pagination manually
        $total = $sorted->count();
        $offset = ($page - 1) * $limit;
        $pageData = $sorted->slice($offset, $limit)->values()->all();

        return new GuildAttendancePagination(
            data: $pageData,
            total: $total,
            perPage: $limit,
            currentPage: $page,
            from: $total > 0 ? $offset + 1 : 0,
            to: min($offset + count($pageData), $total),
            lastPage: $total > 0 ? (int) ceil($total / $limit) : 1,
            hasMorePages: ($offset + $limit) < $total,
        );
    }

    /**
     * Get a LazyCollection that auto-fetches pages of attendance as items are consumed.
     * Memory efficient for iterating over all attendance records.
     *
     * @param  array<string>|null  $playerNames  Filter to only include these players.
     * @param  int|array<int>|null  $guildTagID  Single tag ID or array of tag IDs.
     *
     * @throws GuildNotFoundException
     * @throws GraphQLException
     */
    public function getAttendanceLazy(
        ?int $limit = 25,
        ?array $playerNames = null,
        int|array|null $guildTagID = null,
        ?int $zoneID = null,
    ): LazyCollection {
        return $this->getGuildAttendanceLazy(
            $this->guildId,
            $limit,
            $playerNames,
            $guildTagID,
            $zoneID,
        );
    }

    /**
     * Get a LazyCollection that auto-fetches pages of attendance as items are consumed.
     * Memory efficient for iterating over all attendance records.
     *
     * @param  array<string>|null  $playerNames  Filter to only include these players.
     * @param  int|array<int>|null  $guildTagID  Single tag ID or array of tag IDs.
     *
     * @throws GuildNotFoundException
     * @throws GraphQLException
     */
    public function getGuildAttendanceLazy(
        int $guildId,
        int $limit = 25,
        ?array $playerNames = null,
        int|array|null $guildTagID = null,
        ?int $zoneID = null,
    ): LazyCollection {
        $tagIDs = GuildTags::normalizeGuildTagIDs($guildTagID);

        // No tags or single tag: existing behavior
        if (count($tagIDs) <= 1) {
            return $this->getSingleTagAttendanceLazy(
                $guildId,
                $limit,
                $playerNames,
                $tagIDs[0] ?? null,
                $zoneID,
            );
        }

        // Multiple tags: chain lazy collections, deduplicate
        return $this->paginateLazyAcrossTags(
            $tagIDs,
            fn (int $tagID) => $this->getSingleTagAttendanceLazy(
                $guildId,
                $limit,
                $playerNames,
                $tagID,
                $zoneID,
            ),
        );
    }

    /**
     * Get lazy attendance for a single tag.
     *
     * @param  array<string>|null  $playerNames  Filter to only include these players.
     *
     * @throws GuildNotFoundException
     * @throws GraphQLException
     */
    protected function getSingleTagAttendanceLazy(
        int $guildId,
        int $limit,
        ?array $playerNames,
        ?int $guildTagID,
        ?int $zoneID,
    ): LazyCollection {
        return $this->paginateLazy(function (int $page) use ($guildId, $limit, $playerNames, $guildTagID, $zoneID) {
            $result = $this->querySingleTagAttendance($guildId, $page, $limit, null, $guildTagID, $zoneID);

            $items = [];

            foreach ($result->data as $attendance) {
                // Apply player filter if specified
                if ($playerNames !== null) {
                    $attendance = $attendance->filterPlayers($playerNames);
                    if (empty($attendance->players)) {
                        continue;
                    }
                }

                $items[] = $attendance;
            }

            return ['items' => $items, 'hasMorePages' => $result->hasMorePages];
        });
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
        int $guildTagID,
        ?array $playerNames,
        ?int $zoneID,
        ?int $guildId = null,
        ?int $limit = null,
    ): array {
        $result = $this->querySingleTagAttendance(
            $guildId ?? $this->guildId,
            $page,
            $limit ?? 25,
            null,
            $guildTagID,
            $zoneID,
        );

        $items = [];

        foreach ($result->data as $attendance) {
            if ($playerNames !== null) {
                $attendance = $attendance->filterPlayers($playerNames);
                if (empty($attendance->players)) {
                    continue;
                }
            }

            $items[] = $attendance;
        }

        return ['items' => $items, 'hasMorePages' => $result->hasMorePages];
    }

    /**
     * Build the GraphQL query for fetching guild attendance data.
     */
    protected function buildGraphQuery(?int $guildTagID = null, ?int $zoneID = null): string
    {
        $variableDefinitions = ['$id: Int!', '$page: Int', '$limit: Int'];
        $attendanceArgs = ['page: $page', 'limit: $limit'];

        if ($guildTagID !== null) {
            $variableDefinitions[] = '$guildTagID: Int';
            $attendanceArgs[] = 'guildTagID: $guildTagID';
        }

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
