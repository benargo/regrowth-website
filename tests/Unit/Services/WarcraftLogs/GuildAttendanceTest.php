<?php

namespace Tests\Unit\Services\WarcraftLogs;

use App\Services\WarcraftLogs\Data\GuildAttendance;
use App\Services\WarcraftLogs\Data\GuildAttendancePagination;
use App\Services\WarcraftLogs\Data\PlayerAttendance;
use App\Services\WarcraftLogs\Data\PlayerAttendanceStats;
use App\Services\WarcraftLogs\Exceptions\GuildNotFoundException;
use App\Services\WarcraftLogs\GuildService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use Tests\TestCase;

class GuildAttendanceTest extends TestCase
{
    protected function getService(array $configOverrides = []): GuildService
    {
        $config = array_merge([
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'token_url' => 'https://www.warcraftlogs.com/oauth/token',
            'graphql_url' => 'https://www.warcraftlogs.com/api/v2/client',
            'guild_id' => 774848,
            'timeout' => 30,
            'cache_ttl' => 3600,
        ], $configOverrides);

        return new GuildService($config);
    }

    protected function fakeSuccessfulAttendanceResponse(array $attendanceData): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => [
                    'guildData' => [
                        'guild' => [
                            'attendance' => $attendanceData,
                        ],
                    ],
                ],
            ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');
    }

    protected function sampleAttendanceData(): array
    {
        return [
            'data' => [
                [
                    'code' => 'abc123',
                    'startTime' => 1736971200000, // 2025-01-15 20:00:00 UTC
                    'players' => [
                        ['name' => 'Thrall', 'type' => 'Shaman', 'presence' => 1],
                        ['name' => 'Jaina', 'type' => 'Mage', 'presence' => 1],
                        ['name' => 'Sylvanas', 'type' => 'Hunter', 'presence' => 2],
                    ],
                ],
                [
                    'code' => 'def456',
                    'startTime' => 1736366400000, // 2025-01-08 20:00:00 UTC
                    'players' => [
                        ['name' => 'Thrall', 'type' => 'Shaman', 'presence' => 1],
                        ['name' => 'Anduin', 'type' => 'Priest', 'presence' => 1],
                    ],
                ],
            ],
            'total' => 10,
            'per_page' => 25,
            'current_page' => 1,
            'from' => 1,
            'to' => 2,
            'last_page' => 1,
            'has_more_pages' => false,
        ];
    }

    // ==================== GuildAttendance Tests ====================

    public function test_guild_attendance_can_be_created_from_array(): void
    {
        $data = [
            'code' => 'abc123',
            'startTime' => 1736971200000, // 2025-01-15 20:00:00 UTC
            'players' => [
                ['name' => 'Thrall', 'type' => 'Shaman', 'presence' => 1],
            ],
        ];

        $attendance = GuildAttendance::fromArray($data);

        $this->assertEquals('abc123', $attendance->code);
        $this->assertInstanceOf(Carbon::class, $attendance->startTime);
        $this->assertCount(1, $attendance->players);
        $this->assertInstanceOf(PlayerAttendance::class, $attendance->players[0]);
    }

    public function test_guild_attendance_filter_players_returns_matching_players(): void
    {
        $data = [
            'code' => 'abc123',
            'startTime' => 1736971200000, // 2025-01-15 20:00:00 UTC
            'players' => [
                ['name' => 'Thrall', 'type' => 'Shaman', 'presence' => 1],
                ['name' => 'Jaina', 'type' => 'Mage', 'presence' => 1],
                ['name' => 'Sylvanas', 'type' => 'Hunter', 'presence' => 2],
            ],
        ];

        $attendance = GuildAttendance::fromArray($data);
        $filtered = $attendance->filterPlayers(['Thrall', 'Jaina']);

        $this->assertCount(2, $filtered->players);
        $this->assertEquals('Thrall', $filtered->players[0]->name);
        $this->assertEquals('Jaina', $filtered->players[1]->name);
    }

    public function test_guild_attendance_filter_players_returns_empty_when_no_matches(): void
    {
        $data = [
            'code' => 'abc123',
            'startTime' => 1736971200000, // 2025-01-15 20:00:00 UTC
            'players' => [
                ['name' => 'Thrall', 'type' => 'Shaman', 'presence' => 1],
            ],
        ];

        $attendance = GuildAttendance::fromArray($data);
        $filtered = $attendance->filterPlayers(['NonExistent']);

        $this->assertCount(0, $filtered->players);
    }

    public function test_guild_attendance_filter_players_preserves_other_properties(): void
    {
        $data = [
            'code' => 'abc123',
            'startTime' => 1736971200000, // 2025-01-15 20:00:00 UTC
            'players' => [
                ['name' => 'Thrall', 'type' => 'Shaman', 'presence' => 1],
                ['name' => 'Jaina', 'type' => 'Mage', 'presence' => 1],
            ],
        ];

        $attendance = GuildAttendance::fromArray($data);
        $filtered = $attendance->filterPlayers(['Thrall']);

        $this->assertEquals('abc123', $filtered->code);
        $this->assertEquals($attendance->startTime, $filtered->startTime);
    }

    public function test_guild_attendance_filter_players_is_case_sensitive(): void
    {
        $data = [
            'code' => 'abc123',
            'startTime' => 1736971200000, // 2025-01-15 20:00:00 UTC
            'players' => [
                ['name' => 'Thrall', 'type' => 'Shaman', 'presence' => 1],
            ],
        ];

        $attendance = GuildAttendance::fromArray($data);
        $filtered = $attendance->filterPlayers(['thrall']); // lowercase

        $this->assertCount(0, $filtered->players);
    }

    // ==================== GuildAttendancePagination Tests ====================

    public function test_guild_attendance_pagination_can_be_created_from_array(): void
    {
        $pagination = GuildAttendancePagination::fromArray($this->sampleAttendanceData());

        $this->assertCount(2, $pagination->data);
        $this->assertEquals(10, $pagination->total);
        $this->assertEquals(25, $pagination->perPage);
        $this->assertEquals(1, $pagination->currentPage);
        $this->assertFalse($pagination->hasMorePages);
    }

    public function test_guild_attendance_pagination_converts_to_length_aware_paginator(): void
    {
        $pagination = GuildAttendancePagination::fromArray($this->sampleAttendanceData());
        $paginator = $pagination->toLengthAwarePaginator('/attendance');

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertEquals(10, $paginator->total());
        $this->assertEquals(25, $paginator->perPage());
        $this->assertEquals(1, $paginator->currentPage());
        $this->assertCount(2, $paginator->items());
    }

    public function test_guild_attendance_pagination_to_array(): void
    {
        $pagination = GuildAttendancePagination::fromArray($this->sampleAttendanceData());
        $array = $pagination->toArray();

        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('total', $array);
        $this->assertArrayHasKey('perPage', $array);
        $this->assertArrayHasKey('currentPage', $array);
        $this->assertArrayHasKey('hasMorePages', $array);
    }

    // ==================== GuildService Attendance Tests ====================

    public function test_get_guild_attendance_returns_pagination_object(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $attendance = $service->getGuildAttendance(774848);

        $this->assertInstanceOf(GuildAttendancePagination::class, $attendance);
        $this->assertCount(2, $attendance->data);
    }

    public function test_get_attendance_uses_configured_guild_id(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService(['guild_id' => 774848]);
        $service->getAttendance();

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['variables']['id']) && $body['variables']['id'] === 774848;
        });
    }

    public function test_get_guild_attendance_passes_pagination_parameters(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $service->getGuildAttendance(774848, page: 2, limit: 50);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $body['variables']['page'] === 2 && $body['variables']['limit'] === 50;
        });
    }

    public function test_get_guild_attendance_throws_exception_when_guild_not_found(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => [
                    'guildData' => [
                        'guild' => null,
                    ],
                ],
            ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();

        $this->expectException(GuildNotFoundException::class);

        $service->getGuildAttendance(99999);
    }

    public function test_get_guild_attendance_filters_by_player_names(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $attendance = $service->getGuildAttendance(774848, playerNames: ['Thrall']);

        // First attendance record has Thrall
        $this->assertCount(1, $attendance->data[0]->players);
        $this->assertEquals('Thrall', $attendance->data[0]->players[0]->name);
    }

    public function test_get_guild_attendance_filters_by_date_range(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $attendance = $service->getGuildAttendance(
            774848,
            startDate: Carbon::parse('2025-01-10'),
            endDate: Carbon::parse('2025-01-20'),
        );

        // Only the first record (2025-01-15) should match
        $this->assertCount(1, $attendance->data);
        $this->assertEquals('abc123', $attendance->data[0]->code);
    }

    // ==================== LazyCollection Tests ====================

    public function test_get_guild_attendance_lazy_returns_lazy_collection(): void
    {
        // Note: LazyCollection is lazy, so no HTTP/cache calls happen until iteration
        $service = $this->getService();
        $lazy = $service->getGuildAttendanceLazy(774848);

        $this->assertInstanceOf(LazyCollection::class, $lazy);
    }

    public function test_get_guild_attendance_lazy_iterates_all_records(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $records = $service->getGuildAttendanceLazy(774848)->all();

        $this->assertCount(2, $records);
        $this->assertInstanceOf(GuildAttendance::class, $records[0]);
    }

    public function test_get_guild_attendance_lazy_filters_by_player_names(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $records = $service->getGuildAttendanceLazy(774848, playerNames: ['Anduin'])->all();

        // Only second record has Anduin
        $this->assertCount(1, $records);
        $this->assertEquals('def456', $records[0]->code);
    }

    public function test_get_attendance_lazy_uses_configured_guild_id(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService(['guild_id' => 774848]);
        $service->getAttendanceLazy()->all();

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['variables']['id']) && $body['variables']['id'] === 774848;
        });
    }

    public function test_get_guild_attendance_lazy_fetches_multiple_pages(): void
    {
        Http::preventStrayRequests();

        $page1Data = [
            'data' => [
                [
                    'code' => 'page1',
                    'startTime' => 1736971200000, // 2025-01-15 20:00:00 UTC
                    'players' => [['name' => 'Thrall', 'type' => 'Shaman', 'presence' => 1]],
                ],
            ],
            'total' => 2,
            'per_page' => 1,
            'current_page' => 1,
            'from' => 1,
            'to' => 1,
            'last_page' => 2,
            'has_more_pages' => true,
        ];

        $page2Data = [
            'data' => [
                [
                    'code' => 'page2',
                    'startTime' => 1736366400000, // 2025-01-08 20:00:00 UTC
                    'players' => [['name' => 'Jaina', 'type' => 'Mage', 'presence' => 1]],
                ],
            ],
            'total' => 2,
            'per_page' => 1,
            'current_page' => 2,
            'from' => 2,
            'to' => 2,
            'last_page' => 2,
            'has_more_pages' => false,
        ];

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::sequence()
                ->push([
                    'data' => ['guildData' => ['guild' => ['attendance' => $page1Data]]],
                ], 200)
                ->push([
                    'data' => ['guildData' => ['guild' => ['attendance' => $page2Data]]],
                ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        Cache::shouldReceive('remember')
            ->twice()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $records = $service->getGuildAttendanceLazy(774848, limit: 1)->all();

        $this->assertCount(2, $records);
        $this->assertEquals('page1', $records[0]->code);
        $this->assertEquals('page2', $records[1]->code);
    }

    // ==================== Attendance Stats Calculation Tests ====================

    public function test_calculate_attendance_stats_returns_empty_collection_for_empty_input(): void
    {
        $service = $this->getService();
        $stats = $service->calculateAttendanceStats([]);

        $this->assertInstanceOf(Collection::class, $stats);
        $this->assertEmpty($stats);
    }

    public function test_calculate_attendance_stats_returns_player_attendance_stats_objects(): void
    {
        $attendance = [
            GuildAttendance::fromArray([
                'code' => 'abc123',
                'startTime' => Carbon::parse('2025-01-15')->valueOf(),
                'players' => [
                    ['name' => 'Thrall', 'presence' => 1],
                ],
            ]),
        ];

        $service = $this->getService();
        $stats = $service->calculateAttendanceStats($attendance);

        $this->assertInstanceOf(Collection::class, $stats);
        $this->assertCount(1, $stats);
        $this->assertInstanceOf(PlayerAttendanceStats::class, $stats->first());
        $this->assertEquals('Thrall', $stats->first()->name);
    }

    public function test_calculate_attendance_stats_calculates_100_percent_for_full_attendance(): void
    {
        $attendance = [
            GuildAttendance::fromArray([
                'code' => 'report1',
                'startTime' => Carbon::parse('2025-01-15')->valueOf(),
                'players' => [
                    ['name' => 'Thrall', 'presence' => 1],
                ],
            ]),
            GuildAttendance::fromArray([
                'code' => 'report2',
                'startTime' => Carbon::parse('2025-01-08')->valueOf(),
                'players' => [
                    ['name' => 'Thrall', 'presence' => 1],
                ],
            ]),
        ];

        $service = $this->getService();
        $stats = $service->calculateAttendanceStats($attendance);

        $thrall = $stats->firstWhere('name', 'Thrall');
        $this->assertEquals(100.0, $thrall->percentage);
        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(2, $thrall->reportsAttended);
    }

    public function test_calculate_attendance_stats_calculates_partial_attendance(): void
    {
        $attendance = [
            GuildAttendance::fromArray([
                'code' => 'report1',
                'startTime' => Carbon::parse('2025-01-15')->valueOf(),
                'players' => [
                    ['name' => 'Thrall', 'presence' => 1],
                ],
            ]),
            GuildAttendance::fromArray([
                'code' => 'report2',
                'startTime' => Carbon::parse('2025-01-08')->valueOf(),
                'players' => [
                    ['name' => 'Thrall', 'presence' => 1],
                    ['name' => 'Jaina', 'presence' => 1],
                ],
            ]),
            GuildAttendance::fromArray([
                'code' => 'report3',
                'startTime' => Carbon::parse('2025-01-01')->valueOf(),
                'players' => [
                    ['name' => 'Jaina', 'presence' => 1],
                ],
            ]),
        ];

        $service = $this->getService();
        $stats = $service->calculateAttendanceStats($attendance);

        $jaina = $stats->firstWhere('name', 'Jaina');
        $thrall = $stats->firstWhere('name', 'Thrall');

        // Jaina: first attendance Jan 1, 3 reports since, attended 2 = 66.67%
        $this->assertEquals(66.67, $jaina->percentage);
        $this->assertEquals(3, $jaina->totalReports);
        $this->assertEquals(2, $jaina->reportsAttended);

        // Thrall: first attendance Jan 8, 2 reports since, attended 2 = 100%
        $this->assertEquals(100.0, $thrall->percentage);
        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(2, $thrall->reportsAttended);
    }

    public function test_calculate_attendance_stats_counts_benched_as_attendance(): void
    {
        $attendance = [
            GuildAttendance::fromArray([
                'code' => 'report1',
                'startTime' => Carbon::parse('2025-01-15')->valueOf(),
                'players' => [
                    ['name' => 'Sylvanas', 'presence' => 2], // Benched
                ],
            ]),
            GuildAttendance::fromArray([
                'code' => 'report2',
                'startTime' => Carbon::parse('2025-01-08')->valueOf(),
                'players' => [
                    ['name' => 'Sylvanas', 'presence' => 1], // Present
                ],
            ]),
        ];

        $service = $this->getService();
        $stats = $service->calculateAttendanceStats($attendance);

        $sylvanas = $stats->firstWhere('name', 'Sylvanas');
        $this->assertEquals(100.0, $sylvanas->percentage);
        $this->assertEquals(2, $sylvanas->reportsAttended);
    }

    public function test_calculate_attendance_stats_handles_player_joining_midway(): void
    {
        $jan15 = Carbon::createFromTimestampMs(1736985600000); // 2025-01-15 20:00 UTC
        $jan08 = Carbon::createFromTimestampMs(1736380800000); // 2025-01-08 20:00 UTC
        $jan01 = Carbon::createFromTimestampMs(1735776000000); // 2025-01-01 20:00 UTC

        $attendance = [
            GuildAttendance::fromArray([
                'code' => 'report1',
                'startTime' => $jan15->valueOf(),
                'players' => [
                    ['name' => 'Thrall', 'presence' => 1],
                    ['name' => 'Anduin', 'presence' => 1],
                ],
            ]),
            GuildAttendance::fromArray([
                'code' => 'report2',
                'startTime' => $jan08->valueOf(),
                'players' => [
                    ['name' => 'Thrall', 'presence' => 1],
                ],
            ]),
            GuildAttendance::fromArray([
                'code' => 'report3',
                'startTime' => $jan01->valueOf(),
                'players' => [
                    ['name' => 'Thrall', 'presence' => 1],
                ],
            ]),
        ];

        $service = $this->getService();
        $stats = $service->calculateAttendanceStats($attendance);

        $anduin = $stats->firstWhere('name', 'Anduin');
        $thrall = $stats->firstWhere('name', 'Thrall');

        // Anduin joined on Jan 15, only 1 report since then
        $this->assertTrue($anduin->firstAttendance->eq($jan15));
        $this->assertEquals(1, $anduin->totalReports);
        $this->assertEquals(100.0, $anduin->percentage);

        // Thrall has been there since Jan 1, 3 reports
        $this->assertTrue($thrall->firstAttendance->eq($jan01));
        $this->assertEquals(3, $thrall->totalReports);
    }

    public function test_calculate_attendance_stats_works_with_lazy_collection(): void
    {
        $attendance = LazyCollection::make(function () {
            yield GuildAttendance::fromArray([
                'code' => 'report1',
                'startTime' => Carbon::parse('2025-01-15')->valueOf(),
                'players' => [
                    ['name' => 'Thrall', 'presence' => 1],
                ],
            ]);
            yield GuildAttendance::fromArray([
                'code' => 'report2',
                'startTime' => Carbon::parse('2025-01-08')->valueOf(),
                'players' => [
                    ['name' => 'Thrall', 'presence' => 1],
                ],
            ]);
        });

        $service = $this->getService();
        $stats = $service->calculateAttendanceStats($attendance);

        $this->assertCount(1, $stats);
        $thrall = $stats->firstWhere('name', 'Thrall');
        $this->assertEquals(100.0, $thrall->percentage);
    }

    public function test_calculate_attendance_stats_to_array(): void
    {
        $attendance = [
            GuildAttendance::fromArray([
                'code' => 'report1',
                'startTime' => Carbon::parse('2025-01-15')->valueOf(),
                'players' => [
                    ['name' => 'Thrall', 'presence' => 1],
                ],
            ]),
        ];

        $service = $this->getService();
        $stats = $service->calculateAttendanceStats($attendance);

        $array = $stats->first()->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('firstAttendance', $array);
        $this->assertArrayHasKey('totalReports', $array);
        $this->assertArrayHasKey('reportsAttended', $array);
        $this->assertArrayHasKey('percentage', $array);
    }

    // ==================== Multi-Tag Tests ====================

    public function test_get_guild_attendance_accepts_array_of_tag_ids(): void
    {
        Http::preventStrayRequests();

        // First tag response
        $tag1Data = [
            'data' => [
                [
                    'code' => 'tag1report',
                    'startTime' => 1736971200000,
                    'players' => [['name' => 'Thrall', 'type' => 'Shaman', 'presence' => 1]],
                ],
            ],
            'total' => 1,
            'per_page' => 100,
            'current_page' => 1,
            'from' => 1,
            'to' => 1,
            'last_page' => 1,
            'has_more_pages' => false,
        ];

        // Second tag response
        $tag2Data = [
            'data' => [
                [
                    'code' => 'tag2report',
                    'startTime' => 1736366400000,
                    'players' => [['name' => 'Jaina', 'type' => 'Mage', 'presence' => 1]],
                ],
            ],
            'total' => 1,
            'per_page' => 100,
            'current_page' => 1,
            'from' => 1,
            'to' => 1,
            'last_page' => 1,
            'has_more_pages' => false,
        ];

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::sequence()
                ->push(['data' => ['guildData' => ['guild' => ['attendance' => $tag1Data]]]], 200)
                ->push(['data' => ['guildData' => ['guild' => ['attendance' => $tag2Data]]]], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        Cache::shouldReceive('remember')
            ->twice()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $result = $service->getGuildAttendance(774848, guildTagID: [1, 2]);

        $this->assertInstanceOf(GuildAttendancePagination::class, $result);
        $this->assertCount(2, $result->data);
    }

    public function test_get_guild_attendance_with_multiple_tags_deduplicates_by_report_code(): void
    {
        Http::preventStrayRequests();

        // Both tags return the same report
        $sharedData = [
            'data' => [
                [
                    'code' => 'sharedreport',
                    'startTime' => 1736971200000,
                    'players' => [['name' => 'Thrall', 'type' => 'Shaman', 'presence' => 1]],
                ],
            ],
            'total' => 1,
            'per_page' => 100,
            'current_page' => 1,
            'from' => 1,
            'to' => 1,
            'last_page' => 1,
            'has_more_pages' => false,
        ];

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::sequence()
                ->push(['data' => ['guildData' => ['guild' => ['attendance' => $sharedData]]]], 200)
                ->push(['data' => ['guildData' => ['guild' => ['attendance' => $sharedData]]]], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        Cache::shouldReceive('remember')
            ->twice()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $result = $service->getGuildAttendance(774848, guildTagID: [1, 2]);

        // Should only have 1 record since they share the same code
        $this->assertCount(1, $result->data);
        $this->assertEquals('sharedreport', $result->data[0]->code);
    }

    public function test_get_guild_attendance_with_single_tag_in_array_works_same_as_int(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $result = $service->getGuildAttendance(774848, guildTagID: [1]);

        $this->assertInstanceOf(GuildAttendancePagination::class, $result);
    }

    public function test_get_guild_attendance_lazy_accepts_array_of_tag_ids(): void
    {
        Http::preventStrayRequests();

        $tag1Data = [
            'data' => [
                [
                    'code' => 'lazy1',
                    'startTime' => 1736971200000,
                    'players' => [['name' => 'Thrall', 'type' => 'Shaman', 'presence' => 1]],
                ],
            ],
            'total' => 1,
            'per_page' => 25,
            'current_page' => 1,
            'from' => 1,
            'to' => 1,
            'last_page' => 1,
            'has_more_pages' => false,
        ];

        $tag2Data = [
            'data' => [
                [
                    'code' => 'lazy2',
                    'startTime' => 1736366400000,
                    'players' => [['name' => 'Jaina', 'type' => 'Mage', 'presence' => 1]],
                ],
            ],
            'total' => 1,
            'per_page' => 25,
            'current_page' => 1,
            'from' => 1,
            'to' => 1,
            'last_page' => 1,
            'has_more_pages' => false,
        ];

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::sequence()
                ->push(['data' => ['guildData' => ['guild' => ['attendance' => $tag1Data]]]], 200)
                ->push(['data' => ['guildData' => ['guild' => ['attendance' => $tag2Data]]]], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        Cache::shouldReceive('remember')
            ->twice()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $records = $service->getGuildAttendanceLazy(774848, guildTagID: [1, 2])->all();

        $this->assertCount(2, $records);
    }

    public function test_get_guild_attendance_lazy_with_multiple_tags_deduplicates(): void
    {
        Http::preventStrayRequests();

        $sharedData = [
            'data' => [
                [
                    'code' => 'sharedlazy',
                    'startTime' => 1736971200000,
                    'players' => [['name' => 'Thrall', 'type' => 'Shaman', 'presence' => 1]],
                ],
            ],
            'total' => 1,
            'per_page' => 25,
            'current_page' => 1,
            'from' => 1,
            'to' => 1,
            'last_page' => 1,
            'has_more_pages' => false,
        ];

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::sequence()
                ->push(['data' => ['guildData' => ['guild' => ['attendance' => $sharedData]]]], 200)
                ->push(['data' => ['guildData' => ['guild' => ['attendance' => $sharedData]]]], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        Cache::shouldReceive('remember')
            ->twice()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $records = $service->getGuildAttendanceLazy(774848, guildTagID: [1, 2])->all();

        $this->assertCount(1, $records);
    }

    // ==================== Aggregation Tests ====================

    public function test_aggregate_attendance_stats_returns_empty_for_empty_input(): void
    {
        $service = $this->getService();
        $result = $service->aggregateAttendanceStats(collect());

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result);
    }

    public function test_aggregate_attendance_stats_merges_single_stats_set(): void
    {
        $stats = collect([
            new PlayerAttendanceStats(
                name: 'Thrall',
                firstAttendance: Carbon::parse('2025-01-15'),
                totalReports: 5,
                reportsAttended: 4,
                percentage: 80.0,
            ),
        ]);

        $service = $this->getService();
        $result = $service->aggregateAttendanceStats(collect([$stats]));

        $this->assertCount(1, $result);
        $this->assertEquals('Thrall', $result->first()->name);
        $this->assertEquals(5, $result->first()->totalReports);
    }

    public function test_aggregate_attendance_stats_keeps_earliest_first_attendance(): void
    {
        $stats1 = collect([
            new PlayerAttendanceStats(
                name: 'Thrall',
                firstAttendance: Carbon::parse('2025-01-15'),
                totalReports: 3,
                reportsAttended: 3,
                percentage: 100.0,
            ),
        ]);

        $stats2 = collect([
            new PlayerAttendanceStats(
                name: 'Thrall',
                firstAttendance: Carbon::parse('2025-01-01'), // Earlier
                totalReports: 2,
                reportsAttended: 2,
                percentage: 100.0,
            ),
        ]);

        $service = $this->getService();
        $result = $service->aggregateAttendanceStats(collect([$stats1, $stats2]));

        $thrall = $result->first();
        $this->assertTrue($thrall->firstAttendance->eq(Carbon::parse('2025-01-01')));
    }

    public function test_aggregate_attendance_stats_sums_total_reports(): void
    {
        $stats1 = collect([
            new PlayerAttendanceStats(
                name: 'Thrall',
                firstAttendance: Carbon::parse('2025-01-15'),
                totalReports: 5,
                reportsAttended: 4,
                percentage: 80.0,
            ),
        ]);

        $stats2 = collect([
            new PlayerAttendanceStats(
                name: 'Thrall',
                firstAttendance: Carbon::parse('2025-01-15'),
                totalReports: 3,
                reportsAttended: 2,
                percentage: 66.67,
            ),
        ]);

        $service = $this->getService();
        $result = $service->aggregateAttendanceStats(collect([$stats1, $stats2]));

        $thrall = $result->first();
        $this->assertEquals(8, $thrall->totalReports);
    }

    public function test_aggregate_attendance_stats_sums_reports_attended(): void
    {
        $stats1 = collect([
            new PlayerAttendanceStats(
                name: 'Thrall',
                firstAttendance: Carbon::parse('2025-01-15'),
                totalReports: 5,
                reportsAttended: 4,
                percentage: 80.0,
            ),
        ]);

        $stats2 = collect([
            new PlayerAttendanceStats(
                name: 'Thrall',
                firstAttendance: Carbon::parse('2025-01-15'),
                totalReports: 3,
                reportsAttended: 2,
                percentage: 66.67,
            ),
        ]);

        $service = $this->getService();
        $result = $service->aggregateAttendanceStats(collect([$stats1, $stats2]));

        $thrall = $result->first();
        $this->assertEquals(6, $thrall->reportsAttended);
    }

    public function test_aggregate_attendance_stats_calculates_correct_percentage(): void
    {
        $stats1 = collect([
            new PlayerAttendanceStats(
                name: 'Thrall',
                firstAttendance: Carbon::parse('2025-01-15'),
                totalReports: 5,
                reportsAttended: 4,
                percentage: 80.0,
            ),
        ]);

        $stats2 = collect([
            new PlayerAttendanceStats(
                name: 'Thrall',
                firstAttendance: Carbon::parse('2025-01-15'),
                totalReports: 3,
                reportsAttended: 2,
                percentage: 66.67,
            ),
        ]);

        $service = $this->getService();
        $result = $service->aggregateAttendanceStats(collect([$stats1, $stats2]));

        $thrall = $result->first();
        // 6 attended out of 8 total = 75%
        $this->assertEquals(75.0, $thrall->percentage);
    }

    public function test_aggregate_attendance_stats_handles_player_in_only_one_tag(): void
    {
        $stats1 = collect([
            new PlayerAttendanceStats(
                name: 'Thrall',
                firstAttendance: Carbon::parse('2025-01-15'),
                totalReports: 5,
                reportsAttended: 5,
                percentage: 100.0,
            ),
        ]);

        $stats2 = collect([
            new PlayerAttendanceStats(
                name: 'Jaina',
                firstAttendance: Carbon::parse('2025-01-01'),
                totalReports: 3,
                reportsAttended: 2,
                percentage: 66.67,
            ),
        ]);

        $service = $this->getService();
        $result = $service->aggregateAttendanceStats(collect([$stats1, $stats2]));

        $this->assertCount(2, $result);

        $jaina = $result->firstWhere('name', 'Jaina');
        $thrall = $result->firstWhere('name', 'Thrall');

        $this->assertEquals(5, $thrall->totalReports);
        $this->assertEquals(3, $jaina->totalReports);
    }

    // ==================== calculateMultiTagAttendanceStats Tests ====================

    public function test_calculate_multi_tag_attendance_stats_with_empty_array_returns_empty(): void
    {
        $service = $this->getService();
        $result = $service->calculateMultiTagAttendanceStats([]);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result);
    }

    public function test_calculate_multi_tag_attendance_stats_returns_aggregated_stats(): void
    {
        Http::preventStrayRequests();

        $tag1Data = [
            'data' => [
                [
                    'code' => 'report1',
                    'startTime' => 1736971200000,
                    'players' => [
                        ['name' => 'Thrall', 'type' => 'Shaman', 'presence' => 1],
                    ],
                ],
            ],
            'total' => 1,
            'per_page' => 25,
            'current_page' => 1,
            'from' => 1,
            'to' => 1,
            'last_page' => 1,
            'has_more_pages' => false,
        ];

        $tag2Data = [
            'data' => [
                [
                    'code' => 'report2',
                    'startTime' => 1736366400000,
                    'players' => [
                        ['name' => 'Thrall', 'type' => 'Shaman', 'presence' => 1],
                    ],
                ],
            ],
            'total' => 1,
            'per_page' => 25,
            'current_page' => 1,
            'from' => 1,
            'to' => 1,
            'last_page' => 1,
            'has_more_pages' => false,
        ];

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::sequence()
                ->push(['data' => ['guildData' => ['guild' => ['attendance' => $tag1Data]]]], 200)
                ->push(['data' => ['guildData' => ['guild' => ['attendance' => $tag2Data]]]], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        Cache::shouldReceive('remember')
            ->twice()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $result = $service->calculateMultiTagAttendanceStats([1, 2]);

        $this->assertCount(1, $result);
        $thrall = $result->first();
        $this->assertEquals('Thrall', $thrall->name);
        // 1 report from each tag = 2 total, attended both = 100%
        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(2, $thrall->reportsAttended);
        $this->assertEquals(100.0, $thrall->percentage);
    }

    // ==================== Backwards Compatibility Tests ====================

    public function test_get_guild_attendance_with_null_tag_works_unchanged(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $result = $service->getGuildAttendance(774848, guildTagID: null);

        $this->assertInstanceOf(GuildAttendancePagination::class, $result);
        $this->assertCount(2, $result->data);
    }

    public function test_get_guild_attendance_with_single_int_tag_works_unchanged(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $result = $service->getGuildAttendance(774848, guildTagID: 1);

        $this->assertInstanceOf(GuildAttendancePagination::class, $result);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['variables']['guildTagID']) && $body['variables']['guildTagID'] === 1;
        });
    }
}
