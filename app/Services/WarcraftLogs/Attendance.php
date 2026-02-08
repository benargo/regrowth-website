<?php

namespace App\Services\WarcraftLogs;

use App\Services\WarcraftLogs\Data\GuildAttendance;
use App\Services\WarcraftLogs\Data\GuildAttendancePagination;
use App\Services\WarcraftLogs\Exceptions\GraphQLException;
use App\Services\WarcraftLogs\Exceptions\GuildNotFoundException;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class Attendance extends WarcraftLogsService
{
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
     * Optional start date filter.
     */
    protected ?Carbon $startDate = null;

    /**
     * Optional end date filter.
     */
    protected ?Carbon $endDate = null;

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
     * Set the start date filter.
     */
    public function startDate(?Carbon $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    /**
     * Set the end date filter.
     */
    public function endDate(?Carbon $endDate): static
    {
        $this->endDate = $endDate;

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

        $allRecords = collect();

        foreach ($this->tags as $tagID) {
            $tagAttendance = $this->getAttendanceLazy(
                guildTagID: $tagID,
                startDate: $this->startDate,
                endDate: $this->endDate,
                playerNames: $this->playerNames,
                zoneID: $this->zoneID,
            );

            foreach ($tagAttendance as $attendance) {
                // Use report code as unique key to deduplicate
                $allRecords[$attendance->code] = $attendance;
            }
        }

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

        return LazyCollection::make(function () {
            $seenCodes = [];

            foreach ($this->tags as $tagID) {
                $tagAttendance = $this->getAttendanceLazy(
                    guildTagID: $tagID,
                    startDate: $this->startDate,
                    endDate: $this->endDate,
                    playerNames: $this->playerNames,
                    zoneID: $this->zoneID,
                );

                foreach ($tagAttendance as $attendance) {
                    // Skip duplicates (same report from different tags)
                    if (isset($seenCodes[$attendance->code])) {
                        continue;
                    }
                    $seenCodes[$attendance->code] = true;
                    yield $attendance;
                }
            }
        });
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
     * @param  array{page?: int, limit?: int, startDate?: Carbon, endDate?: Carbon, playerNames?: array<string>, guildTagID?: int|array<int>, zoneID?: int}  $params
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
            $params['startDate'] ?? null,
            $params['endDate'] ?? null,
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
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
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
                $startDate,
                $endDate,
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
            $startDate,
            $endDate,
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
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
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

        // Apply filters if specified
        if ($startDate !== null || $endDate !== null || $playerNames !== null) {
            $filteredData = array_filter(
                $pagination->data,
                function (GuildAttendance $attendance) use ($startDate, $endDate) {
                    if ($startDate !== null && $attendance->startTime->lt($startDate)) {
                        return false;
                    }
                    if ($endDate !== null && $attendance->startTime->gt($endDate)) {
                        return false;
                    }

                    return true;
                },
            );

            // Apply player filter to each attendance record
            if ($playerNames !== null) {
                $filteredData = array_map(
                    fn (GuildAttendance $attendance) => $attendance->filterPlayers($playerNames),
                    $filteredData,
                );
                // Remove attendance records with no matching players
                $filteredData = array_filter(
                    $filteredData,
                    fn (GuildAttendance $attendance) => ! empty($attendance->players),
                );
            }

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
        ?Carbon $startDate,
        ?Carbon $endDate,
        ?array $playerNames,
        array $guildTagIDs,
        ?int $zoneID,
    ): GuildAttendancePagination {
        $allRecords = collect();

        foreach ($guildTagIDs as $tagID) {
            // Fetch all pages for this tag to enable proper merging
            $tagLazy = $this->getSingleTagAttendanceLazy(
                $guildId,
                100,
                $startDate,
                $endDate,
                $playerNames,
                $tagID,
                $zoneID,
            );

            foreach ($tagLazy as $attendance) {
                // Use report code as unique key to deduplicate
                $allRecords[$attendance->code] = $attendance;
            }
        }

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
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?array $playerNames = null,
        int|array|null $guildTagID = null,
        ?int $zoneID = null,
    ): LazyCollection {
        return $this->getGuildAttendanceLazy(
            $this->guildId,
            $limit,
            $startDate,
            $endDate,
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
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
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
                $startDate,
                $endDate,
                $playerNames,
                $tagIDs[0] ?? null,
                $zoneID,
            );
        }

        // Multiple tags: chain lazy collections, deduplicate
        return $this->getMultiTagAttendanceLazy(
            $guildId,
            $limit,
            $startDate,
            $endDate,
            $playerNames,
            $tagIDs,
            $zoneID,
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
        ?Carbon $startDate,
        ?Carbon $endDate,
        ?array $playerNames,
        ?int $guildTagID,
        ?int $zoneID,
    ): LazyCollection {
        return LazyCollection::make(function () use ($guildId, $limit, $startDate, $endDate, $playerNames, $guildTagID, $zoneID) {
            $page = 1;

            do {
                $result = $this->querySingleTagAttendance($guildId, $page, $limit, null, null, null, $guildTagID, $zoneID);

                foreach ($result->data as $attendance) {
                    // Apply date filters
                    if ($startDate !== null && $attendance->startTime->lt($startDate)) {
                        continue;
                    }
                    if ($endDate !== null && $attendance->startTime->gt($endDate)) {
                        // Assuming records are ordered by date descending, stop iteration
                        return;
                    }

                    // Apply player filter if specified
                    if ($playerNames !== null) {
                        $attendance = $attendance->filterPlayers($playerNames);
                        // Skip if no matching players in this attendance record
                        if (empty($attendance->players)) {
                            continue;
                        }
                    }

                    yield $attendance;
                }

                $page++;
            } while ($result->hasMorePages);
        });
    }

    /**
     * Get attendance lazily for multiple tags, deduplicating by report code.
     *
     * @param  array<string>|null  $playerNames  Filter to only include these players.
     * @param  array<int>  $guildTagIDs
     *
     * @throws GuildNotFoundException
     * @throws GraphQLException
     */
    protected function getMultiTagAttendanceLazy(
        int $guildId,
        int $limit,
        ?Carbon $startDate,
        ?Carbon $endDate,
        ?array $playerNames,
        array $guildTagIDs,
        ?int $zoneID,
    ): LazyCollection {
        return LazyCollection::make(function () use (
            $guildId, $limit, $startDate, $endDate, $playerNames, $guildTagIDs, $zoneID
        ) {
            $seenCodes = [];

            foreach ($guildTagIDs as $tagID) {
                $tagLazy = $this->getSingleTagAttendanceLazy(
                    $guildId,
                    $limit,
                    $startDate,
                    $endDate,
                    $playerNames,
                    $tagID,
                    $zoneID,
                );

                foreach ($tagLazy as $attendance) {
                    // Skip duplicates (same report from different tags)
                    if (isset($seenCodes[$attendance->code])) {
                        continue;
                    }
                    $seenCodes[$attendance->code] = true;
                    yield $attendance;
                }
            }
        });
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
