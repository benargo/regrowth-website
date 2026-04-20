<?php

namespace Tests\Unit\Services\Attendance;

use App\Models\Character;
use App\Models\GuildRank;
use App\Services\Attendance\CharacterAttendanceStats;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JsonSerializable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterAttendanceStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Europe/Paris']);
    }

    protected function makeStats(array $overrides = []): CharacterAttendanceStats
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create([
            'name' => 'Thrall',
            'rank_id' => $rank->id,
        ]);

        return new CharacterAttendanceStats(
            character: $overrides['character'] ?? $character,
            firstAttendance: $overrides['firstAttendance'] ?? Carbon::parse('2024-01-15 20:00:00', 'UTC'),
            totalReports: $overrides['totalReports'] ?? 20,
            reportsAttended: $overrides['reportsAttended'] ?? 15,
            percentage: $overrides['percentage'] ?? 75.0,
        );
    }

    #[Test]
    public function it_exposes_typed_properties(): void
    {
        $stats = $this->makeStats();

        $this->assertInstanceOf(Character::class, $stats->character);
        $this->assertInstanceOf(Carbon::class, $stats->firstAttendance);
        $this->assertSame(20, $stats->totalReports);
        $this->assertSame(15, $stats->reportsAttended);
        $this->assertSame(75.0, $stats->percentage);
    }

    #[Test]
    public function it_implements_arrayable_and_json_serializable(): void
    {
        $stats = $this->makeStats();

        $this->assertInstanceOf(Arrayable::class, $stats);
        $this->assertInstanceOf(JsonSerializable::class, $stats);
    }

    #[Test]
    public function to_array_includes_character_id_and_name(): void
    {
        $stats = $this->makeStats();

        $array = $stats->toArray();

        $this->assertSame($stats->character->id, $array['id']);
        $this->assertSame('Thrall', $array['name']);
        $this->assertSame(20, $array['totalReports']);
        $this->assertSame(15, $array['reportsAttended']);
        $this->assertSame(75.0, $array['percentage']);
    }

    #[Test]
    public function to_array_formats_first_attendance_as_iso8601(): void
    {
        $stats = $this->makeStats([
            'firstAttendance' => Carbon::parse('2024-06-01 18:30:00', 'UTC'),
        ]);

        $array = $stats->toArray();

        $this->assertIsString($array['firstAttendance']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $array['firstAttendance']);
    }

    #[Test]
    public function to_array_converts_first_attendance_to_app_timezone(): void
    {
        config(['app.timezone' => 'Australia/Sydney']);

        $stats = $this->makeStats([
            'firstAttendance' => Carbon::parse('2024-01-15 12:00:00', 'UTC'),
        ]);

        $array = $stats->toArray();

        $this->assertStringContainsString('23:00:00', $array['firstAttendance']);
    }

    #[Test]
    public function json_serialize_matches_to_array(): void
    {
        $stats = $this->makeStats();

        $this->assertSame($stats->toArray(), $stats->jsonSerialize());
    }
}
