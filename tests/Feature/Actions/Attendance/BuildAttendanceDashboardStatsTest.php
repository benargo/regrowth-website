<?php

namespace Tests\Feature\Actions\Attendance;

use App\Actions\Attendance\BuildAttendanceDashboardStats;
use App\Data\Attendance\BenchedCharacterData;
use App\Enums\PlannedAbsenceReason;
use App\Enums\Presence;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\Phase;
use App\Models\PlannedAbsence;
use App\Models\PlayableClass;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsAttendanceFixtures;
use Tests\TestCase;

#[Group('raiding')]
class BuildAttendanceDashboardStatsTest extends TestCase
{
    use BuildsAttendanceFixtures;
    use RefreshDatabase;

    private GameVersion $gameVersion;

    private GuildRank $rank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-17 12:00:00'));
        Cache::tags(['attendance', 'reports'])->flush();

        $this->gameVersion = $this->createAttendanceVersion();
        $this->rank = $this->createRank($this->gameVersion);
    }

    #[Test]
    #[DataProvider('bandCases')]
    public function it_bands_each_main_by_attendance_percentage(int $attended, int $total, string $band): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank, ['name' => 'Thrall', 'is_main' => true]);
        $tag = $this->createTag($this->gameVersion);

        for ($i = 0; $i < $total; $i++) {
            $report = $this->createReport($this->gameVersion, now()->subDays(60 - $i), $tag);

            if ($i < $attended) {
                $this->attend($report, $character);
            }
        }

        $stats = BuildAttendanceDashboardStats::run($this->gameVersion);

        $this->assertSame([$band], $stats->percentageGroups->keys()->all());
        $this->assertSame(['Thrall'], $stats->percentageGroups->get($band)->pluck('name')->all());
    }

    /**
     * @return array<string, array{int, int, string}>
     */
    public static function bandCases(): array
    {
        return [
            '80% is in the top band' => [4, 5, '>=80'],
            'just under 80% is in the middle band' => [15, 19, '50-80'],
            '50% is in the middle band' => [1, 2, '50-80'],
            'under 50% is in the bottom band' => [2, 5, '<50'],
        ];
    }

    #[Test]
    public function it_leaves_a_non_main_out_of_every_band_but_counts_it_as_linked(): void
    {
        $alt = $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => false]);
        $this->attend($this->createReport($this->gameVersion, now()->subDay()), $alt);

        $stats = BuildAttendanceDashboardStats::run($this->gameVersion);

        $this->assertTrue($stats->percentageGroups->isEmpty());
        $this->assertSame(1, $stats->totalPlayers);
        $this->assertSame(0, $stats->totalMains);
        $this->assertSame(1, $stats->totalLinkedCharacters);
    }

    // ==================== totals ====================

    #[Test]
    public function it_counts_mains_and_linked_characters_separately(): void
    {
        $report = $this->createReport($this->gameVersion, now()->subDay());
        $this->attend($report, $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true]));
        $this->attend($report, $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true]));
        $this->attend($report, $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => false]));

        $stats = BuildAttendanceDashboardStats::run($this->gameVersion);

        $this->assertSame(3, $stats->totalPlayers);
        $this->assertSame(2, $stats->totalMains);
        $this->assertSame(1, $stats->totalLinkedCharacters);
    }

    #[Test]
    public function it_counts_zero_players_when_the_version_has_no_counting_ranks(): void
    {
        $this->rank->update(['count_attendance' => false]);
        $this->attend($this->createReport($this->gameVersion, now()->subDay()), $this->createCharacter($this->gameVersion, $this->rank));

        $this->assertSame(0, BuildAttendanceDashboardStats::run($this->gameVersion)->totalPlayers);
    }

    // ==================== trends ====================

    #[Test]
    public function it_lists_the_characters_dropping_off(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank, ['name' => 'Vashj', 'is_main' => true]);

        for ($i = 0; $i < 4; $i++) {
            $this->attend($this->createReport($this->gameVersion, now()->subDays(20 + $i)), $character);
            $this->createReport($this->gameVersion, now()->subDays(1 + $i));
        }

        $stats = BuildAttendanceDashboardStats::run($this->gameVersion);

        $this->assertSame(['Vashj'], $stats->droppingOff->pluck('name')->all());
        $this->assertTrue($stats->pickingUp->isEmpty());
    }

    #[Test]
    public function it_lists_the_characters_picking_up(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank, ['name' => 'Maiev', 'is_main' => true]);
        $this->attend($this->createReport($this->gameVersion, now()->subDays(30)), $character);

        for ($i = 0; $i < 3; $i++) {
            $this->createReport($this->gameVersion, now()->subDays(15 + $i));
        }

        for ($i = 0; $i < 4; $i++) {
            $this->attend($this->createReport($this->gameVersion, now()->subDays(1 + $i)), $character);
        }

        $stats = BuildAttendanceDashboardStats::run($this->gameVersion);

        $this->assertSame(['Maiev'], $stats->pickingUp->pluck('name')->all());
        $this->assertTrue($stats->droppingOff->isEmpty());
    }

    #[Test]
    public function its_character_lists_carry_only_the_playable_class_relation(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true]);
        $this->attend($this->createReport($this->gameVersion, now()->subDay()), $character);

        $listed = BuildAttendanceDashboardStats::run($this->gameVersion)->percentageGroups->get('>=80')->sole();

        $this->assertSame(['playableClass'], array_keys($listed->getRelations()));
    }

    // ==================== phase attendance ====================

    #[Test]
    public function its_phase_attendances_are_null_when_no_phase_has_started(): void
    {
        $this->createReport($this->gameVersion, now()->subDay());

        $stats = BuildAttendanceDashboardStats::run($this->gameVersion);

        $this->assertNull($stats->phaseAttendance);
        $this->assertNull($stats->previousPhaseAttendance);
    }

    #[Test]
    public function its_phase_attendance_only_counts_reports_since_the_current_phase_start(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true]);
        Phase::factory()->forGameVersion($this->gameVersion)->create(['start_date' => now()->subDays(14)]);
        $this->attend($this->createReport($this->gameVersion, now()->subDays(20)), $character);
        $this->attend($this->createReport($this->gameVersion, now()->subDays(10)), $character);
        $this->createReport($this->gameVersion, now()->subDays(5));

        $this->assertSame(50.0, BuildAttendanceDashboardStats::run($this->gameVersion)->phaseAttendance);
    }

    #[Test]
    public function its_previous_phase_attendance_is_bounded_between_the_phase_starts(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true]);
        Phase::factory()->forGameVersion($this->gameVersion)->create(['number' => 1, 'start_date' => now()->subDays(30)]);
        Phase::factory()->forGameVersion($this->gameVersion)->create(['number' => 2, 'start_date' => now()->subDays(7)]);
        $this->attend($this->createReport($this->gameVersion, now()->subDays(20)), $character);
        $this->createReport($this->gameVersion, now()->subDays(3));

        $stats = BuildAttendanceDashboardStats::run($this->gameVersion);

        $this->assertSame(100.0, $stats->previousPhaseAttendance);
        $this->assertNull($stats->phaseAttendance);
    }

    #[Test]
    public function its_phase_attendance_counts_the_launch_day_raid_in_the_current_phase_only(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true]);
        Phase::factory()->forGameVersion($this->gameVersion)->create(['number' => 1, 'start_date' => '2026-09-01 00:00:00']);
        Phase::factory()->forGameVersion($this->gameVersion)->create(['number' => 2, 'start_date' => '2026-10-01 00:00:00']);
        $this->attend($this->createReport($this->gameVersion, '2026-10-01 20:00'), $character);
        $this->createReport($this->gameVersion, '2026-09-30 20:00');

        $this->assertSame(100.0, BuildAttendanceDashboardStats::run($this->gameVersion)->phaseAttendance);
    }

    #[Test]
    public function its_previous_phase_attendance_counts_the_day_before_launch_but_not_the_launch_day(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true]);
        Phase::factory()->forGameVersion($this->gameVersion)->create(['number' => 1, 'start_date' => '2026-09-01 00:00:00']);
        Phase::factory()->forGameVersion($this->gameVersion)->create(['number' => 2, 'start_date' => '2026-10-01 00:00:00']);
        $this->attend($this->createReport($this->gameVersion, '2026-09-30 20:00'), $character);
        $this->createReport($this->gameVersion, '2026-10-01 20:00');

        $this->assertSame(100.0, BuildAttendanceDashboardStats::run($this->gameVersion)->previousPhaseAttendance);
    }

    #[Test]
    public function its_phase_attendance_excludes_another_versions_reports_and_phases(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true]);
        Phase::factory()->forGameVersion($this->gameVersion)->create(['start_date' => now()->subDays(14)]);
        $this->attend($this->createReport($this->gameVersion, now()->subDays(10)), $character);
        $other = $this->createAttendanceVersion();
        Phase::factory()->forGameVersion($other)->create(['start_date' => now()->subDays(3)]);
        $this->createReport($other, now()->subDays(2));

        $stats = BuildAttendanceDashboardStats::run($this->gameVersion);

        $this->assertSame(100.0, $stats->phaseAttendance);
        $this->assertNull($stats->previousPhaseAttendance);
    }

    // ==================== benched and absences ====================

    #[Test]
    public function it_groups_the_characters_benched_in_the_last_week_by_tag(): void
    {
        $kael = $this->createCharacter($this->gameVersion, $this->rank, ['name' => 'Kael', 'is_main' => true]);
        $illidan = $this->createCharacter($this->gameVersion, $this->rank, ['name' => 'Illidan', 'is_main' => true]);
        $this->attend($this->createReport($this->gameVersion, now()->subDays(2), $this->createTag($this->gameVersion, attributes: ['name' => 'Monday'])), $kael, Presence::Benched);
        $this->attend($this->createReport($this->gameVersion, now()->subDays(1), $this->createTag($this->gameVersion, attributes: ['name' => 'Tuesday'])), $illidan, Presence::Benched);
        $this->attend($this->createReport($this->gameVersion, now()->subDays(10)), $illidan, Presence::Benched);

        $benched = BuildAttendanceDashboardStats::run($this->gameVersion)->benchedLastWeek;

        $this->assertSame(['Monday', 'Tuesday'], $benched->keys()->all());
        $this->assertSame(['Kael'], $benched->get('Monday')->map(fn (BenchedCharacterData $data): string => $data->character->name)->all());
        $this->assertSame(['Illidan'], $benched->get('Tuesday')->map(fn (BenchedCharacterData $data): string => $data->character->name)->all());
    }

    #[Test]
    public function it_lists_the_versions_upcoming_absences_in_start_order_without_a_limit(): void
    {
        $characters = collect(range(1, 6))->map(fn (int $day): Character => $this->createCharacter($this->gameVersion, $this->rank, ['name' => "Character {$day}"]));
        foreach ($characters->reverse() as $index => $character) {
            PlannedAbsence::factory()->create(['character_id' => $character->id, 'start_date' => now()->addDays($index + 1), 'end_date' => now()->addDays($index + 2)]);
        }
        PlannedAbsence::factory()->create(['character_id' => $characters->first()->id, 'start_date' => now()->subDays(5), 'end_date' => now()->subDays(3)]);
        PlannedAbsence::factory()->create([
            'character_id' => Character::factory()->forGameVersion(GameVersion::factory()->create())->create()->id,
            'start_date' => now()->addDay(),
        ]);

        $absences = BuildAttendanceDashboardStats::run($this->gameVersion)->upcomingAbsences;

        $this->assertSame(
            ['Character 1', 'Character 2', 'Character 3', 'Character 4', 'Character 5', 'Character 6'],
            $absences->map(fn (PlannedAbsence $absence): string => $absence->character->name)->all(),
        );
    }

    #[Test]
    public function its_upcoming_absences_carry_the_reason_and_the_characters_playable_class(): void
    {
        $character = Character::factory()->forGameVersion($this->gameVersion)
            ->withPlayableClass(PlayableClass::factory()->create(['name' => 'Warrior']))
            ->create();
        PlannedAbsence::factory()->create(['character_id' => $character->id, 'start_date' => now()->addDay(), 'reason' => PlannedAbsenceReason::Holiday]);

        $absence = BuildAttendanceDashboardStats::run($this->gameVersion)->upcomingAbsences->sole();

        $this->assertSame(PlannedAbsenceReason::Holiday, $absence->reason->key);
        $this->assertSame('Warrior', $absence->character->playableClass->name);
    }
}
