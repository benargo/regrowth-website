<?php

namespace Tests\Unit\Services\WarcraftLogs;

use App\Services\WarcraftLogs\Attendance;
use App\Services\WarcraftLogs\AuthenticationHandler;
use App\Services\WarcraftLogs\Data\GuildAttendance;
use App\Services\WarcraftLogs\Data\GuildAttendancePagination;
use App\Services\WarcraftLogs\Data\PlayerAttendance;
use App\Services\WarcraftLogs\Exceptions\GuildNotFoundException;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    protected function makeService(array $configOverrides = []): Attendance
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

        $auth = new AuthenticationHandler($config['client_id'], $config['client_secret']);

        return new Attendance($config, $auth);
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

        $this->fakeNotRateLimited();
    }

    protected function fakeNotRateLimited(): void
    {
        Cache::shouldReceive('has')
            ->with('warcraftlogs.rate_limited')
            ->andReturn(false);
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
                    'zone' => ['id' => 1, 'name' => 'Test Zone'],
                ],
                [
                    'code' => 'def456',
                    'startTime' => 1736366400000, // 2025-01-08 20:00:00 UTC
                    'players' => [
                        ['name' => 'Thrall', 'type' => 'Shaman', 'presence' => 1],
                        ['name' => 'Anduin', 'type' => 'Priest', 'presence' => 1],
                    ],
                    'zone' => ['id' => 2, 'name' => 'Another Zone'],
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

    protected function createGuildAttendance(string $code, int $startTimeMs, array $playerNames): GuildAttendance
    {
        $players = array_map(
            fn (string $name) => new PlayerAttendance($name, 1),
            $playerNames,
        );

        return new GuildAttendance(
            code: $code,
            players: $players,
            startTime: Carbon::createFromTimestampMs($startTimeMs),
        );
    }

    // ==================== Fluent Builder Tests ====================

    public function test_tags_returns_self_for_chaining(): void
    {
        $service = $this->makeService();
        $result = $service->tags([1, 2]);

        $this->assertSame($service, $result);
    }

    public function test_start_date_returns_self_for_chaining(): void
    {
        $service = $this->makeService();
        $result = $service->startDate(Carbon::now());

        $this->assertSame($service, $result);
    }

    public function test_end_date_returns_self_for_chaining(): void
    {
        $service = $this->makeService();
        $result = $service->endDate(Carbon::now());

        $this->assertSame($service, $result);
    }

    public function test_player_names_returns_self_for_chaining(): void
    {
        $service = $this->makeService();
        $result = $service->playerNames(['Thrall', 'Jaina']);

        $this->assertSame($service, $result);
    }

    public function test_zone_id_returns_self_for_chaining(): void
    {
        $service = $this->makeService();
        $result = $service->zoneID(1);

        $this->assertSame($service, $result);
    }

    public function test_set_attendance_returns_self_for_chaining(): void
    {
        $service = $this->makeService();
        $result = $service->setAttendance([]);

        $this->assertSame($service, $result);
    }

    public function test_fluent_methods_can_be_chained(): void
    {
        $service = $this->makeService();

        $result = $service
            ->tags([1, 2])
            ->startDate(Carbon::now()->subMonth())
            ->endDate(Carbon::now())
            ->playerNames(['Thrall'])
            ->zoneID(1);

        $this->assertSame($service, $result);
    }

    // ==================== setAttendance and get() Tests ====================

    public function test_get_returns_collection_of_guild_attendance(): void
    {
        $attendance = [
            $this->createGuildAttendance('abc123', 1736971200000, ['Thrall']),
            $this->createGuildAttendance('def456', 1736366400000, ['Jaina']),
        ];

        $service = $this->makeService();
        $result = $service->setAttendance($attendance)->get();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(GuildAttendance::class, $result);
    }

    public function test_get_returns_empty_collection_when_no_attendance_set(): void
    {
        $service = $this->makeService();
        $result = $service->setAttendance([])->get();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    public function test_get_sorts_attendance_by_start_time_ascending(): void
    {
        $older = $this->createGuildAttendance('older', 1736366400000, ['Thrall']); // Jan 8
        $newer = $this->createGuildAttendance('newer', 1736971200000, ['Jaina']); // Jan 15

        // Pass in wrong order
        $service = $this->makeService();
        $result = $service->setAttendance([$newer, $older])->get();

        $this->assertEquals('older', $result[0]->code);
        $this->assertEquals('newer', $result[1]->code);
    }

    public function test_get_with_tags_fetches_from_api(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $result = $service->tags([1])->get();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }

    public function test_get_with_multiple_tags_deduplicates_by_report_code(): void
    {
        Http::preventStrayRequests();

        $sharedData = [
            'data' => [
                [
                    'code' => 'sharedreport',
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
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => ['guildData' => ['guild' => ['attendance' => $sharedData]]],
            ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        Cache::shouldReceive('has')
            ->with('warcraftlogs.rate_limited')
            ->andReturn(false);

        Cache::shouldReceive('remember')
            ->twice()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $result = $service->tags([1, 2])->get();

        $this->assertCount(1, $result);
        $this->assertEquals('sharedreport', $result[0]->code);
    }

    public function test_get_with_multiple_tags_merges_unique_reports(): void
    {
        Http::preventStrayRequests();

        $tag1Data = [
            'data' => [
                [
                    'code' => 'tag1report',
                    'startTime' => 1736971200000, // Jan 15
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
                    'code' => 'tag2report',
                    'startTime' => 1736366400000, // Jan 8
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

        Cache::shouldReceive('has')
            ->with('warcraftlogs.rate_limited')
            ->andReturn(false);

        Cache::shouldReceive('remember')
            ->twice()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $result = $service->tags([1, 2])->get();

        $this->assertCount(2, $result);
        // Sorted by startTime ascending
        $this->assertEquals('tag2report', $result[0]->code); // Jan 8
        $this->assertEquals('tag1report', $result[1]->code); // Jan 15
    }

    // ==================== lazy() Tests ====================

    public function test_lazy_returns_lazy_collection(): void
    {
        $service = $this->makeService();
        $result = $service->setAttendance([])->lazy();

        $this->assertInstanceOf(LazyCollection::class, $result);
    }

    public function test_lazy_yields_attendance_records(): void
    {
        $attendance = [
            $this->createGuildAttendance('abc123', 1736971200000, ['Thrall']),
            $this->createGuildAttendance('def456', 1736366400000, ['Jaina']),
        ];

        $service = $this->makeService();
        $result = $service->setAttendance($attendance)->lazy()->all();

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(GuildAttendance::class, $result);
    }

    public function test_lazy_with_tags_fetches_from_api(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $result = $service->tags([1])->lazy()->all();

        $this->assertCount(2, $result);
    }

    public function test_lazy_with_multiple_tags_deduplicates_by_report_code(): void
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
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => ['guildData' => ['guild' => ['attendance' => $sharedData]]],
            ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        Cache::shouldReceive('has')
            ->with('warcraftlogs.rate_limited')
            ->andReturn(false);

        Cache::shouldReceive('remember')
            ->twice()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $result = $service->tags([1, 2])->lazy()->all();

        $this->assertCount(1, $result);
    }

    // ==================== getPlayerFirstAttendanceDate() Tests ====================

    public function test_get_player_first_attendance_date_returns_carbon_for_existing_player(): void
    {
        $attendance = [
            $this->createGuildAttendance('abc123', 1736971200000, ['Jaina']), // Jan 15
            $this->createGuildAttendance('def456', 1736366400000, ['Thrall', 'Jaina']), // Jan 8
        ];

        $service = $this->makeService();
        $result = $service->setAttendance($attendance)->getPlayerFirstAttendanceDate('Jaina');

        $this->assertInstanceOf(Carbon::class, $result);
        // Returns first record found (abc123), not sorted
        $this->assertEquals(1736971200000, $result->valueOf());
    }

    public function test_get_player_first_attendance_date_returns_null_for_nonexistent_player(): void
    {
        $attendance = [
            $this->createGuildAttendance('abc123', 1736971200000, ['Thrall']),
        ];

        $service = $this->makeService();
        $result = $service->setAttendance($attendance)->getPlayerFirstAttendanceDate('NonExistent');

        $this->assertNull($result);
    }

    public function test_get_player_first_attendance_date_returns_null_when_no_attendance(): void
    {
        $service = $this->makeService();
        $result = $service->setAttendance([])->getPlayerFirstAttendanceDate('Thrall');

        $this->assertNull($result);
    }

    public function test_get_player_first_attendance_date_is_case_sensitive(): void
    {
        $attendance = [
            $this->createGuildAttendance('abc123', 1736971200000, ['Thrall']),
        ];

        $service = $this->makeService();
        $result = $service->setAttendance($attendance)->getPlayerFirstAttendanceDate('thrall');

        $this->assertNull($result);
    }

    // ==================== API Method Tests - getAttendance() ====================

    public function test_get_attendance_returns_pagination_object(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $result = $service->getAttendance();

        $this->assertInstanceOf(GuildAttendancePagination::class, $result);
        $this->assertCount(2, $result->data);
    }

    public function test_get_attendance_accepts_pagination_parameters(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $service->getAttendance(['page' => 2, 'limit' => 50]);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $body['variables']['page'] === 2 && $body['variables']['limit'] === 50;
        });
    }

    public function test_get_attendance_passes_guild_tag_id(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $service->getAttendance(['guildTagID' => 1]);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['variables']['guildTagID']) && $body['variables']['guildTagID'] === 1;
        });
    }

    public function test_get_attendance_passes_zone_id(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $service->getAttendance(['zoneID' => 1]);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['variables']['zoneID']) && $body['variables']['zoneID'] === 1;
        });
    }

    public function test_get_attendance_filters_by_start_date(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $result = $service->getAttendance(['startDate' => Carbon::parse('2025-01-10')]);

        // Only abc123 (Jan 15) should be included
        $this->assertCount(1, $result->data);
        $this->assertEquals('abc123', $result->data[0]->code);
    }

    public function test_get_attendance_filters_by_end_date(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $result = $service->getAttendance(['endDate' => Carbon::parse('2025-01-10')]);

        // Only def456 (Jan 8) should be included
        $this->assertCount(1, $result->data);
        $this->assertEquals('def456', $result->data[0]->code);
    }

    public function test_get_attendance_filters_by_player_names(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $result = $service->getAttendance(['playerNames' => ['Anduin']]);

        // Only def456 has Anduin
        $this->assertCount(1, $result->data);
        $this->assertEquals('def456', $result->data[0]->code);
        $this->assertCount(1, $result->data[0]->players);
    }

    // ==================== API Method Tests - getGuildAttendance() ====================

    public function test_get_guild_attendance_returns_pagination_for_specific_guild(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $result = $service->getGuildAttendance(774848);

        $this->assertInstanceOf(GuildAttendancePagination::class, $result);
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

        Cache::shouldReceive('has')
            ->with('warcraftlogs.rate_limited')
            ->andReturn(false);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();

        $this->expectException(GuildNotFoundException::class);

        $service->getGuildAttendance(99999);
    }

    public function test_get_guild_attendance_accepts_array_of_tag_ids(): void
    {
        Http::preventStrayRequests();

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

        Cache::shouldReceive('has')
            ->with('warcraftlogs.rate_limited')
            ->andReturn(false);

        Cache::shouldReceive('remember')
            ->twice()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $result = $service->getGuildAttendance(774848, guildTagID: [1, 2]);

        $this->assertCount(2, $result->data);
    }

    public function test_get_guild_attendance_with_multiple_tags_deduplicates(): void
    {
        Http::preventStrayRequests();

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
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => ['guildData' => ['guild' => ['attendance' => $sharedData]]],
            ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        Cache::shouldReceive('has')
            ->with('warcraftlogs.rate_limited')
            ->andReturn(false);

        Cache::shouldReceive('remember')
            ->twice()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $result = $service->getGuildAttendance(774848, guildTagID: [1, 2]);

        $this->assertCount(1, $result->data);
    }

    public function test_get_guild_attendance_with_multiple_tags_paginates_merged_results(): void
    {
        Http::preventStrayRequests();

        // Create more records than the requested limit
        $tag1Data = [
            'data' => [
                ['code' => 'r1', 'startTime' => 1736971200000, 'players' => []],
                ['code' => 'r2', 'startTime' => 1736884800000, 'players' => []],
            ],
            'total' => 2,
            'per_page' => 100,
            'current_page' => 1,
            'from' => 1,
            'to' => 2,
            'last_page' => 1,
            'has_more_pages' => false,
        ];

        $tag2Data = [
            'data' => [
                ['code' => 'r3', 'startTime' => 1736798400000, 'players' => []],
                ['code' => 'r4', 'startTime' => 1736712000000, 'players' => []],
            ],
            'total' => 2,
            'per_page' => 100,
            'current_page' => 1,
            'from' => 1,
            'to' => 2,
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

        Cache::shouldReceive('has')
            ->with('warcraftlogs.rate_limited')
            ->andReturn(false);

        Cache::shouldReceive('remember')
            ->twice()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $result = $service->getGuildAttendance(774848, page: 1, limit: 2, guildTagID: [1, 2]);

        $this->assertCount(2, $result->data);
        $this->assertEquals(4, $result->total);
        $this->assertTrue($result->hasMorePages);
        $this->assertEquals(2, $result->lastPage);
    }

    // ==================== API Method Tests - getAttendanceLazy() ====================

    public function test_get_attendance_lazy_returns_lazy_collection(): void
    {
        $service = $this->makeService();
        $result = $service->getAttendanceLazy();

        $this->assertInstanceOf(LazyCollection::class, $result);
    }

    public function test_get_attendance_lazy_iterates_all_records(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $records = $service->getAttendanceLazy()->all();

        $this->assertCount(2, $records);
    }

    public function test_get_attendance_lazy_filters_by_date_range(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $records = $service->getAttendanceLazy(
            startDate: Carbon::parse('2025-01-10'),
            endDate: Carbon::parse('2025-01-20'),
        )->all();

        // Only abc123 (Jan 15) matches
        $this->assertCount(1, $records);
        $this->assertEquals('abc123', $records[0]->code);
    }

    public function test_get_attendance_lazy_filters_by_player_names(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $records = $service->getAttendanceLazy(playerNames: ['Anduin'])->all();

        // Only def456 has Anduin
        $this->assertCount(1, $records);
        $this->assertEquals('def456', $records[0]->code);
    }

    public function test_get_attendance_lazy_accepts_array_of_tag_ids(): void
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

        Cache::shouldReceive('has')
            ->with('warcraftlogs.rate_limited')
            ->andReturn(false);

        Cache::shouldReceive('remember')
            ->twice()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $records = $service->getAttendanceLazy(guildTagID: [1, 2])->all();

        $this->assertCount(2, $records);
    }

    // ==================== API Method Tests - getGuildAttendanceLazy() ====================

    public function test_get_guild_attendance_lazy_returns_lazy_collection(): void
    {
        $service = $this->makeService();
        $result = $service->getGuildAttendanceLazy(774848);

        $this->assertInstanceOf(LazyCollection::class, $result);
    }

    public function test_get_guild_attendance_lazy_fetches_multiple_pages(): void
    {
        Http::preventStrayRequests();

        $page1Data = [
            'data' => [
                [
                    'code' => 'page1',
                    'startTime' => 1736971200000,
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
                    'startTime' => 1736366400000,
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
                ->push(['data' => ['guildData' => ['guild' => ['attendance' => $page1Data]]]], 200)
                ->push(['data' => ['guildData' => ['guild' => ['attendance' => $page2Data]]]], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        Cache::shouldReceive('has')
            ->with('warcraftlogs.rate_limited')
            ->andReturn(false);

        Cache::shouldReceive('remember')
            ->twice()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $records = $service->getGuildAttendanceLazy(774848, limit: 1)->all();

        $this->assertCount(2, $records);
        $this->assertEquals('page1', $records[0]->code);
        $this->assertEquals('page2', $records[1]->code);
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
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => ['guildData' => ['guild' => ['attendance' => $sharedData]]],
            ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        Cache::shouldReceive('has')
            ->with('warcraftlogs.rate_limited')
            ->andReturn(false);

        Cache::shouldReceive('remember')
            ->twice()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $records = $service->getGuildAttendanceLazy(774848, guildTagID: [1, 2])->all();

        $this->assertCount(1, $records);
    }

    // ==================== Backwards Compatibility Tests ====================

    public function test_get_guild_attendance_with_null_tag_works(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $result = $service->getGuildAttendance(774848, guildTagID: null);

        $this->assertInstanceOf(GuildAttendancePagination::class, $result);
    }

    public function test_get_guild_attendance_with_single_int_tag_works(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $result = $service->getGuildAttendance(774848, guildTagID: 1);

        $this->assertInstanceOf(GuildAttendancePagination::class, $result);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['variables']['guildTagID']) && $body['variables']['guildTagID'] === 1;
        });
    }

    public function test_get_guild_attendance_with_single_tag_array_uses_single_tag_logic(): void
    {
        $this->fakeSuccessfulAttendanceResponse($this->sampleAttendanceData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->makeService();
        $result = $service->getGuildAttendance(774848, guildTagID: [1]);

        $this->assertInstanceOf(GuildAttendancePagination::class, $result);
    }
}
