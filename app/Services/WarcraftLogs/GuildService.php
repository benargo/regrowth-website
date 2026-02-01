<?php

namespace App\Services\WarcraftLogs;

use App\Models\WarcraftLogs\GuildTag;
use App\Services\WarcraftLogs\Data\Guild;
use App\Services\WarcraftLogs\Data\GuildAttendance;
use App\Services\WarcraftLogs\Data\GuildAttendancePagination;
use App\Services\WarcraftLogs\Data\PlayerAttendanceStats;
use App\Services\WarcraftLogs\Exceptions\GraphQLException;
use App\Services\WarcraftLogs\Exceptions\GuildNotFoundException;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class GuildService extends WarcraftLogsService
{
    protected int $cacheTtl = 43200; // 12 hours

    /**
     * Normalize guild tag ID(s) to an array.
     *
     * @param  int|array<int>|null  $guildTagID
     * @return array<int>
     */
    protected function normalizeGuildTagIDs(int|array|null $guildTagID): array
    {
        if ($guildTagID === null) {
            return [];
        }

        return is_array($guildTagID) ? $guildTagID : [$guildTagID];
    }

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
     * Get guild tags from the API, falling back to database if API returns none.
     *
     * @return Collection<int, GuildTag>
     *
     * @throws GuildNotFoundException
     * @throws GraphQLException
     */
    public function getGuildTags(): Collection
    {
        $this->getGuild();

        return GuildTag::all();
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
            $this->getGuildId(),
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
        $tagIDs = $this->normalizeGuildTagIDs($guildTagID);

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
        $query = $this->buildAttendanceQuery($guildTagID, $zoneID);

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
            $data = $this->queryData($query, $variables);
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
            $this->getGuildId(),
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
        $tagIDs = $this->normalizeGuildTagIDs($guildTagID);

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
     * Calculate attendance statistics for each player in the provided attendance data.
     *
     * For each player, calculates their attendance percentage based on reports
     * since their first appearance. Players not present in a report are considered absent.
     * Presence values of 1 (present) and 2 (benched) both count as valid attendance.
     *
     * @param  iterable<GuildAttendance>  $attendance  Filtered attendance records to analyze.
     * @return Collection<PlayerAttendanceStats> Attendance stats for each player, keyed by name.
     */
    public function calculateAttendanceStats(iterable $attendance): Collection
    {
        // Collect all attendance records and sort by date ascending
        $records = collect($attendance)->sortBy(fn (GuildAttendance $a) => $a->startTime)->values();

        if ($records->isEmpty()) {
            return collect();
        }

        // First pass: find each player's earliest attendance
        /** @var array<string, array{firstAttendance: \Carbon\Carbon}> $playerInfo */
        $playerInfo = [];

        foreach ($records as $record) {
            foreach ($record->players as $player) {
                if (! isset($playerInfo[$player->name])) {
                    $playerInfo[$player->name] = [
                        'firstAttendance' => $record->startTime,
                    ];
                }
            }
        }

        // Second pass: for each player, count reports since their first attendance
        $stats = [];

        foreach ($playerInfo as $playerName => $info) {
            $totalReports = 0;
            $reportsAttended = 0;

            foreach ($records as $record) {
                // Only count reports on or after the player's first attendance
                if ($record->startTime->lt($info['firstAttendance'])) {
                    continue;
                }

                $totalReports++;

                // Check if player attended this report (presence 1 or 2)
                foreach ($record->players as $player) {
                    if ($player->name === $playerName && in_array($player->presence, [1, 2], true)) {
                        $reportsAttended++;
                        break;
                    }
                }
            }

            $percentage = $totalReports > 0 ? ($reportsAttended / $totalReports) * 100 : 0.0;

            $stats[$playerName] = new PlayerAttendanceStats(
                name: $playerName,
                firstAttendance: $info['firstAttendance'],
                totalReports: $totalReports,
                reportsAttended: $reportsAttended,
                percentage: round($percentage, 2),
            );
        }

        return new Collection($stats)
            ->sortBy(fn (PlayerAttendanceStats $s) => $s->name)
            ->values();
    }

    /**
     * Aggregate PlayerAttendanceStats from multiple sources.
     *
     * For each player:
     * - Keep the earliest `firstAttendance` across all sources
     * - Sum `totalReports` from all sources
     * - Sum `reportsAttended` from all sources
     * - Recalculate percentage from summed values
     *
     * @param  Collection<int, Collection<int, PlayerAttendanceStats>>  $statsSets
     * @return Collection<int, PlayerAttendanceStats>
     */
    public function aggregateAttendanceStats(Collection $statsSets): Collection
    {
        /** @var array<string, array{firstAttendance: Carbon, totalReports: int, reportsAttended: int}> $aggregated */
        $aggregated = [];

        foreach ($statsSets as $statsCollection) {
            foreach ($statsCollection as $stats) {
                $name = $stats->name;

                if (! isset($aggregated[$name])) {
                    $aggregated[$name] = [
                        'firstAttendance' => $stats->firstAttendance,
                        'totalReports' => $stats->totalReports,
                        'reportsAttended' => $stats->reportsAttended,
                    ];
                } else {
                    // Keep earliest firstAttendance
                    if ($stats->firstAttendance->lt($aggregated[$name]['firstAttendance'])) {
                        $aggregated[$name]['firstAttendance'] = $stats->firstAttendance;
                    }
                    // Sum reports
                    $aggregated[$name]['totalReports'] += $stats->totalReports;
                    $aggregated[$name]['reportsAttended'] += $stats->reportsAttended;
                }
            }
        }

        // Convert to PlayerAttendanceStats objects
        $result = [];
        foreach ($aggregated as $name => $data) {
            $percentage = $data['totalReports'] > 0
                ? round(($data['reportsAttended'] / $data['totalReports']) * 100, 2)
                : 0.0;

            $result[$name] = new PlayerAttendanceStats(
                name: $name,
                firstAttendance: $data['firstAttendance'],
                totalReports: $data['totalReports'],
                reportsAttended: $data['reportsAttended'],
                percentage: $percentage,
            );
        }

        return collect($result)->sortBy(fn (PlayerAttendanceStats $s) => $s->name)->values();
    }

    /**
     * Calculate attendance stats across multiple guild tags.
     *
     * @param  array<int>  $guildTagIDs
     * @param  array<string>|null  $playerNames  Filter to only include these players.
     * @return Collection<int, PlayerAttendanceStats>
     *
     * @throws GuildNotFoundException
     * @throws GraphQLException
     */
    public function calculateMultiTagAttendanceStats(
        array $guildTagIDs,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?array $playerNames = null,
        ?int $zoneID = null,
    ): Collection {
        if (empty($guildTagIDs)) {
            return collect();
        }

        $statsSets = collect();

        foreach ($guildTagIDs as $tagID) {
            $attendance = $this->getAttendanceLazy(
                guildTagID: $tagID,
                startDate: $startDate,
                endDate: $endDate,
                playerNames: $playerNames,
                zoneID: $zoneID,
            );

            $stats = $this->calculateAttendanceStats($attendance);
            $statsSets->push($stats);
        }

        return $this->aggregateAttendanceStats($statsSets);
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

    /**
     * Build the GraphQL query for fetching guild attendance data.
     */
    protected function buildAttendanceQuery(?int $guildTagID = null, ?int $zoneID = null): string
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
