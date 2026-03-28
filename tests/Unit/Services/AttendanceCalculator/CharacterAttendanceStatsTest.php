<?php

namespace Tests\Unit\Services\AttendanceCalculator;

use App\Services\AttendanceCalculator\CharacterAttendanceStats;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterAttendanceStatsTest extends TestCase
{
    #[Test]
    public function it_converts_to_array_with_correct_structure(): void
    {
        $firstAttendance = Carbon::parse('2024-01-15 20:00:00', 'UTC');

        $stats = new CharacterAttendanceStats(
            id: 42,
            name: 'Thrall',
            firstAttendance: $firstAttendance,
            totalReports: 20,
            reportsAttended: 15,
            percentage: 75.0,
        );

        $result = $stats->toArray();

        $this->assertSame(42, $result['id']);
        $this->assertSame('Thrall', $result['name']);
        $this->assertSame(20, $result['totalReports']);
        $this->assertSame(15, $result['reportsAttended']);
        $this->assertSame(75.0, $result['percentage']);
        $this->assertIsString($result['firstAttendance']);
    }

    #[Test]
    public function it_formats_first_attendance_as_iso8601(): void
    {
        $firstAttendance = Carbon::parse('2024-06-01 18:30:00', 'UTC');

        $stats = new CharacterAttendanceStats(
            id: 1,
            name: 'Jaina',
            firstAttendance: $firstAttendance,
            totalReports: 10,
            reportsAttended: 8,
            percentage: 80.0,
        );

        $result = $stats->toArray();

        // Should contain ISO8601 date markers
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $result['firstAttendance']);
    }

    #[Test]
    public function it_converts_timezone_for_first_attendance(): void
    {
        config(['app.timezone' => 'Australia/Sydney']);

        $firstAttendance = Carbon::parse('2024-01-15 12:00:00', 'UTC');

        $stats = new CharacterAttendanceStats(
            id: 1,
            name: 'Arthas',
            firstAttendance: $firstAttendance,
            totalReports: 5,
            reportsAttended: 3,
            percentage: 60.0,
        );

        $result = $stats->toArray();

        // UTC 12:00 should be 23:00 AEDT (+11)
        $this->assertStringContainsString('23:00:00', $result['firstAttendance']);
    }
}
