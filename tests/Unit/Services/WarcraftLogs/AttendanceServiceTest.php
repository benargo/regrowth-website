<?php

namespace Tests\Unit\Services\WarcraftLogs;

use App\Services\WarcraftLogs\AttendanceService;
use App\Services\WarcraftLogs\Data\GuildAttendance;
use App\Services\WarcraftLogs\Data\PlayerAttendance;
use App\Services\WarcraftLogs\GuildService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Tests\TestCase;

class AttendanceServiceTest extends TestCase
{
    protected function mockGuildService(): \Mockery\MockInterface
    {
        return \Mockery::mock(GuildService::class);
    }

    protected function makeService(
        iterable $attendance = [],
        ?GuildService $guildService = null,
    ): AttendanceService {
        return new AttendanceService(
            guildService: $guildService ?? $this->mockGuildService(),
            attendance: $attendance,
        );
    }

    protected function makeAttendance(string $code, Carbon $startTime, array $players): GuildAttendance
    {
        return new GuildAttendance(
            code: $code,
            startTime: $startTime,
            players: array_map(
                fn (array $p) => new PlayerAttendance(name: $p['name'], presence: $p['presence']),
                $players,
            ),
        );
    }

    // ==================== Fluent Setter Tests ====================

    public function test_tags_returns_same_instance(): void
    {
        $service = $this->makeService();

        $this->assertSame($service, $service->tags([1, 2]));
    }

    public function test_start_date_returns_same_instance(): void
    {
        $service = $this->makeService();

        $this->assertSame($service, $service->startDate(Carbon::now()));
    }

    public function test_end_date_returns_same_instance(): void
    {
        $service = $this->makeService();

        $this->assertSame($service, $service->endDate(Carbon::now()));
    }

    public function test_player_names_returns_same_instance(): void
    {
        $service = $this->makeService();

        $this->assertSame($service, $service->playerNames(['Thrall']));
    }

    public function test_zone_id_returns_same_instance(): void
    {
        $service = $this->makeService();

        $this->assertSame($service, $service->zoneID(42));
    }

    public function test_setters_are_chainable(): void
    {
        $service = $this->makeService();

        $result = $service
            ->tags([1])
            ->startDate(Carbon::parse('2025-01-01'))
            ->endDate(Carbon::parse('2025-01-31'))
            ->playerNames(['Thrall'])
            ->zoneID(100);

        $this->assertSame($service, $result);
    }

    // ==================== Attendance Method Tests ====================

    public function test_attendance_returns_empty_collection_for_empty_input(): void
    {
        $result = $this->makeService([])->attendance();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_attendance_returns_records_sorted_ascending_by_start_time(): void
    {
        $jan01 = Carbon::parse('2025-01-01');
        $jan08 = Carbon::parse('2025-01-08');
        $jan15 = Carbon::parse('2025-01-15');

        // Intentionally out of order
        $attendance = [
            $this->makeAttendance('report_jan15', $jan15, [['name' => 'Thrall', 'presence' => 1]]),
            $this->makeAttendance('report_jan01', $jan01, [['name' => 'Thrall', 'presence' => 1]]),
            $this->makeAttendance('report_jan08', $jan08, [['name' => 'Thrall', 'presence' => 1]]),
        ];

        $result = $this->makeService($attendance)->attendance();

        $this->assertEquals('report_jan01', $result[0]->code);
        $this->assertEquals('report_jan08', $result[1]->code);
        $this->assertEquals('report_jan15', $result[2]->code);
    }

    public function test_attendance_preserves_all_records_from_input(): void
    {
        $attendance = [
            $this->makeAttendance('report1', Carbon::parse('2025-01-01'), [['name' => 'Thrall', 'presence' => 1]]),
            $this->makeAttendance('report2', Carbon::parse('2025-01-08'), [['name' => 'Jaina', 'presence' => 1]]),
            $this->makeAttendance('report3', Carbon::parse('2025-01-15'), [['name' => 'Sylvanas', 'presence' => 2]]),
        ];

        $this->assertCount(3, $this->makeService($attendance)->attendance());
    }

    // ==================== Presence Filtering Tests ====================

    public function test_calculate_does_not_count_presence_zero_as_attendance(): void
    {
        $attendance = [
            $this->makeAttendance('report1', Carbon::parse('2025-01-01'), [['name' => 'Thrall', 'presence' => 1]]),
            $this->makeAttendance('report2', Carbon::parse('2025-01-08'), [['name' => 'Thrall', 'presence' => 0]]),
        ];

        $thrall = $this->makeService($attendance)->calculate()->firstWhere('name', 'Thrall');

        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(1, $thrall->reportsAttended);
        $this->assertEquals(50.0, $thrall->percentage);
    }

    public function test_calculate_does_not_count_presence_outside_1_and_2(): void
    {
        $attendance = [
            $this->makeAttendance('report1', Carbon::parse('2025-01-01'), [['name' => 'Thrall', 'presence' => 1]]),
            $this->makeAttendance('report2', Carbon::parse('2025-01-08'), [['name' => 'Thrall', 'presence' => 3]]),
        ];

        $thrall = $this->makeService($attendance)->calculate()->firstWhere('name', 'Thrall');

        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(1, $thrall->reportsAttended);
        $this->assertEquals(50.0, $thrall->percentage);
    }

    public function test_calculate_counts_both_presence_1_and_2_as_attended(): void
    {
        $attendance = [
            $this->makeAttendance('report1', Carbon::parse('2025-01-01'), [['name' => 'Thrall', 'presence' => 1]]),
            $this->makeAttendance('report2', Carbon::parse('2025-01-08'), [['name' => 'Thrall', 'presence' => 2]]),
            $this->makeAttendance('report3', Carbon::parse('2025-01-15'), [['name' => 'Thrall', 'presence' => 0]]),
        ];

        $thrall = $this->makeService($attendance)->calculate()->firstWhere('name', 'Thrall');

        $this->assertEquals(3, $thrall->totalReports);
        $this->assertEquals(2, $thrall->reportsAttended);
        $this->assertEquals(66.67, $thrall->percentage);
    }

    public function test_calculate_player_with_only_invalid_presence_has_zero_percent(): void
    {
        $attendance = [
            $this->makeAttendance('report1', Carbon::parse('2025-01-01'), [['name' => 'Thrall', 'presence' => 0]]),
            $this->makeAttendance('report2', Carbon::parse('2025-01-08'), [['name' => 'Thrall', 'presence' => 3]]),
        ];

        $thrall = $this->makeService($attendance)->calculate()->firstWhere('name', 'Thrall');

        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(0, $thrall->reportsAttended);
        $this->assertEquals(0.0, $thrall->percentage);
    }

    // ==================== First Attendance Date Tests ====================

    // public function test_get_player_first_attendance_date_returns_null_before_calculate(): void
    // {
    //     $service = $this->makeService([
    //         $this->makeAttendance('report1', Carbon::parse('2025-01-15'), [['name' => 'Thrall', 'presence' => 1]]),
    //     ]);

    //     $this->assertNull($service->getPlayerFirstAttendanceDate('Thrall'));
    // }

    // public function test_get_player_first_attendance_date_returns_earliest_date_after_calculate(): void
    // {
    //     $jan08 = Carbon::parse('2025-01-08');
    //     $jan15 = Carbon::parse('2025-01-15');

    //     // Unsorted: jan15 listed first — first attendance should still resolve to jan08
    //     $service = $this->makeService([
    //         $this->makeAttendance('report1', $jan15, [['name' => 'Thrall', 'presence' => 1]]),
    //         $this->makeAttendance('report2', $jan08, [['name' => 'Thrall', 'presence' => 1]]),
    //     ]);
    //     $service->calculate();

    //     $this->assertTrue($service->getPlayerFirstAttendanceDate('Thrall')->eq($jan08));
    // }

    // public function test_get_player_first_attendance_date_returns_null_for_unknown_player(): void
    // {
    //     $service = $this->makeService([
    //         $this->makeAttendance('report1', Carbon::parse('2025-01-15'), [['name' => 'Thrall', 'presence' => 1]]),
    //     ]);
    //     $service->calculate();

    //     $this->assertNull($service->getPlayerFirstAttendanceDate('NonExistent'));
    // }

    // ==================== Result Sorting Tests ====================

    public function test_calculate_results_are_sorted_alphabetically_by_name(): void
    {
        $attendance = [
            $this->makeAttendance('report1', Carbon::parse('2025-01-01'), [
                ['name' => 'Zara', 'presence' => 1],
                ['name' => 'Alice', 'presence' => 1],
                ['name' => 'Milo', 'presence' => 1],
            ]),
        ];

        $stats = $this->makeService($attendance)->calculate();

        $this->assertEquals('Alice', $stats[0]->name);
        $this->assertEquals('Milo', $stats[1]->name);
        $this->assertEquals('Zara', $stats[2]->name);
    }

    // ==================== Absent Player Tests ====================

    public function test_calculate_player_absent_after_first_appearance(): void
    {
        $attendance = [
            $this->makeAttendance('report1', Carbon::parse('2025-01-01'), [
                ['name' => 'Jaina', 'presence' => 1],
                ['name' => 'Thrall', 'presence' => 1],
            ]),
            $this->makeAttendance('report2', Carbon::parse('2025-01-08'), [
                ['name' => 'Thrall', 'presence' => 1],
            ]),
            $this->makeAttendance('report3', Carbon::parse('2025-01-15'), [
                ['name' => 'Thrall', 'presence' => 1],
            ]),
        ];

        $jaina = $this->makeService($attendance)->calculate()->firstWhere('name', 'Jaina');

        $this->assertEquals(3, $jaina->totalReports);
        $this->assertEquals(1, $jaina->reportsAttended);
        $this->assertEquals(33.33, $jaina->percentage);
    }

    public function test_calculate_single_player_single_report(): void
    {
        $attendance = [
            $this->makeAttendance('report1', Carbon::parse('2025-01-15'), [
                ['name' => 'Thrall', 'presence' => 1],
            ]),
        ];

        $thrall = $this->makeService($attendance)->calculate()->first();

        $this->assertEquals(1, $thrall->totalReports);
        $this->assertEquals(1, $thrall->reportsAttended);
        $this->assertEquals(100.0, $thrall->percentage);
    }

    // ==================== Tag Delegation Tests ====================

    public function test_calculate_without_tags_does_not_call_guild_service(): void
    {
        $guildService = $this->mockGuildService();
        $guildService->shouldReceive('getAttendanceLazy')->never();

        $attendance = [
            $this->makeAttendance('report1', Carbon::parse('2025-01-15'), [
                ['name' => 'Thrall', 'presence' => 1],
            ]),
        ];

        $stats = (new AttendanceService(guildService: $guildService, attendance: $attendance))->calculate();

        $this->assertCount(1, $stats);
        $this->assertEquals('Thrall', $stats->first()->name);
    }

    public function test_calculate_with_tags_calls_guild_service_once_per_tag(): void
    {
        $guildService = $this->mockGuildService();
        $guildService->shouldReceive('getAttendanceLazy')
            ->twice()
            ->andReturn(LazyCollection::make([]));

        (new AttendanceService(guildService: $guildService))->tags([10, 20])->calculate();
    }

    public function test_calculate_with_tags_ignores_constructor_attendance(): void
    {
        $guildService = $this->mockGuildService();
        $guildService->shouldReceive('getAttendanceLazy')
            ->once()
            ->andReturn(LazyCollection::make([
                $this->makeAttendance('tag_report', Carbon::parse('2025-01-08'), [
                    ['name' => 'Jaina', 'presence' => 1],
                ]),
            ]));

        $stats = (new AttendanceService(
            guildService: $guildService,
            attendance: [
                $this->makeAttendance('constructor_report', Carbon::parse('2025-01-01'), [
                    ['name' => 'Thrall', 'presence' => 1],
                ]),
            ],
        ))->tags([1])->calculate();

        // Only Jaina from the tag source should appear; Thrall from constructor is ignored
        $this->assertCount(1, $stats);
        $this->assertEquals('Jaina', $stats->first()->name);
    }

    public function test_calculate_with_tags_aggregates_results_across_tags(): void
    {
        $tag1Report = $this->makeAttendance('report1', Carbon::parse('2025-01-01'), [
            ['name' => 'Thrall', 'presence' => 1],
        ]);
        $tag2Report = $this->makeAttendance('report2', Carbon::parse('2025-01-08'), [
            ['name' => 'Thrall', 'presence' => 1],
        ]);

        $guildService = $this->mockGuildService();
        $guildService->shouldReceive('getAttendanceLazy')
            ->once()
            ->ordered()
            ->andReturn(LazyCollection::make([$tag1Report]));
        $guildService->shouldReceive('getAttendanceLazy')
            ->once()
            ->ordered()
            ->andReturn(LazyCollection::make([$tag2Report]));

        $stats = (new AttendanceService(guildService: $guildService))->tags([10, 20])->calculate();

        $thrall = $stats->firstWhere('name', 'Thrall');
        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(2, $thrall->reportsAttended);
        $this->assertEquals(100.0, $thrall->percentage);
    }

    public function test_calculate_with_single_tag_returns_stats_from_that_tag(): void
    {
        $guildService = $this->mockGuildService();
        $guildService->shouldReceive('getAttendanceLazy')
            ->once()
            ->andReturn(LazyCollection::make([
                $this->makeAttendance('report1', Carbon::parse('2025-01-01'), [
                    ['name' => 'Thrall', 'presence' => 1],
                ]),
                $this->makeAttendance('report2', Carbon::parse('2025-01-08'), [
                    ['name' => 'Thrall', 'presence' => 1],
                ]),
            ]));

        $stats = (new AttendanceService(guildService: $guildService))->tags([5])->calculate();

        $thrall = $stats->first();
        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(2, $thrall->reportsAttended);
        $this->assertEquals(100.0, $thrall->percentage);
    }
}
