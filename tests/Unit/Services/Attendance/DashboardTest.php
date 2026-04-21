<?php

namespace Tests\Unit\Services\Attendance;

use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Models\Raids\Report;
use App\Models\TBC\Phase;
use App\Models\WarcraftLogs\GuildTag;
use App\Services\Attendance\Calculator;
use App\Services\Attendance\Dashboard;
use App\Services\Attendance\DataTable;
use App\Services\Attendance\FiltersData;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::tags(['attendance', 'reports'])->flush();
        Cache::tags(['attendance'])->flush();
    }

    protected function makeDashboard(): Dashboard
    {
        $calculator = new Calculator;
        $filters = new FiltersData;
        $table = new DataTable($calculator, $filters);

        return new Dashboard($calculator, $table);
    }

    protected function makeTag(): GuildTag
    {
        return GuildTag::factory()->countsAttendance()->withoutPhase()->create();
    }

    protected function makeRank(): GuildRank
    {
        return GuildRank::factory()->create();
    }

    protected function makeReport(GuildTag $tag, Carbon $startTime): Report
    {
        return Report::factory()->withGuildTag($tag)->create(['start_time' => $startTime]);
    }

    protected function attachCharacter(Report $report, Character $character, int $presence): void
    {
        $report->characters()->attach($character->id, ['presence' => $presence]);
    }

    // ==================== latestReportDate ====================

    #[Test]
    public function latest_report_date_returns_null_when_no_reports_exist(): void
    {
        $dashboard = $this->makeDashboard();

        $this->assertNull($dashboard->latestReportDate());
    }

    #[Test]
    public function latest_report_date_returns_formatted_date_of_most_recent_report(): void
    {
        $tag = $this->makeTag();
        $this->makeReport($tag, Carbon::parse('2025-03-07 20:00:00'));
        $this->makeReport($tag, Carbon::parse('2025-03-15 20:00:00'));
        $this->makeReport($tag, Carbon::parse('2025-03-10 20:00:00'));

        $dashboard = $this->makeDashboard();

        $this->assertSame('15 Mar 2025', $dashboard->latestReportDate());
    }

    #[Test]
    public function latest_report_date_is_cached(): void
    {
        $tag = $this->makeTag();
        $this->makeReport($tag, Carbon::parse('2025-06-01 20:00:00'));

        $dashboard = $this->makeDashboard();
        $dashboard->latestReportDate();

        // Add a newer report — cached value should not change
        $this->makeReport($tag, Carbon::parse('2025-06-30 20:00:00'));

        $this->assertSame('01 Jun 2025', $dashboard->latestReportDate());
    }

    // ==================== stats: structure ====================

    #[Test]
    public function stats_returns_all_expected_keys(): void
    {
        $dashboard = $this->makeDashboard();

        $stats = $dashboard->stats();

        $this->assertArrayHasKey('percentageGroups', $stats);
        $this->assertArrayHasKey('droppingOff', $stats);
        $this->assertArrayHasKey('pickingUp', $stats);
        $this->assertArrayHasKey('totalPlayers', $stats);
        $this->assertArrayHasKey('totalMains', $stats);
        $this->assertArrayHasKey('totalLinkedCharacters', $stats);
        $this->assertArrayHasKey('phaseAttendance', $stats);
        $this->assertArrayHasKey('previousPhaseAttendance', $stats);
        $this->assertArrayHasKey('benchedLastWeek', $stats);
    }

    #[Test]
    public function stats_returns_zero_counts_when_no_data(): void
    {
        $stats = $this->makeDashboard()->stats();

        $this->assertSame(0, $stats['totalPlayers']);
        $this->assertSame(0, $stats['totalMains']);
        $this->assertSame(0, $stats['totalLinkedCharacters']);
        $this->assertEmpty($stats['percentageGroups']);
        $this->assertEmpty($stats['droppingOff']);
        $this->assertEmpty($stats['pickingUp']);
        $this->assertEmpty($stats['benchedLastWeek']);
    }

    // ==================== stats: percentageGroups ====================

    #[Test]
    public function stats_classifies_main_above_80_percent(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->main()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        for ($i = 0; $i < 5; $i++) {
            $report = $this->makeReport($tag, now()->subDays(10 + $i));
            $this->attachCharacter($report, $character, 1);
        }

        $stats = $this->makeDashboard()->stats();

        $this->assertCount(1, $stats['percentageGroups']['>=80']);
        $this->assertSame('Thrall', $stats['percentageGroups']['>=80'][0]['name']);
    }

    #[Test]
    public function stats_classifies_main_between_50_and_80_percent(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->main()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        // Debut raid (attended) — establishes first attendance date
        $debut = $this->makeReport($tag, now()->subDays(20));
        $this->attachCharacter($debut, $character, 1);

        // 2 more attended raids
        for ($i = 0; $i < 2; $i++) {
            $report = $this->makeReport($tag, now()->subDays(10 + $i));
            $this->attachCharacter($report, $character, 1);
        }

        // 2 raids not attended (after debut) = 3/5 = 60%
        for ($i = 0; $i < 2; $i++) {
            $this->makeReport($tag, now()->subDays(14 + $i));
        }

        $stats = $this->makeDashboard()->stats();

        $this->assertCount(1, $stats['percentageGroups']['50-80']);
        $this->assertSame('Jaina', $stats['percentageGroups']['50-80'][0]['name']);
    }

    #[Test]
    public function stats_classifies_main_below_50_percent(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->main()->create(['name' => 'Sylvanas', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        // Debut raid (attended) — establishes first attendance date
        $debut = $this->makeReport($tag, now()->subDays(20));
        $this->attachCharacter($debut, $character, 1);

        // 4 subsequent raids not attended = 1/5 = 20%
        for ($i = 0; $i < 4; $i++) {
            $this->makeReport($tag, now()->subDays(5 + $i));
        }

        $stats = $this->makeDashboard()->stats();

        $this->assertCount(1, $stats['percentageGroups']['<50']);
        $this->assertSame('Sylvanas', $stats['percentageGroups']['<50'][0]['name']);
    }

    #[Test]
    public function stats_excludes_linked_characters_from_percentage_groups(): void
    {
        $rank = $this->makeRank();
        $linked = Character::factory()->create(['is_main' => false, 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $report = $this->makeReport($tag, now()->subDays(1));
        $this->attachCharacter($report, $linked, 1);

        $stats = $this->makeDashboard()->stats();

        $this->assertEmpty($stats['percentageGroups']);
    }

    #[Test]
    public function stats_percentage_group_player_has_name_and_playable_class(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->main()->create(['name' => 'Arthas', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $report = $this->makeReport($tag, now()->subDays(1));
        $this->attachCharacter($report, $character, 1);

        $stats = $this->makeDashboard()->stats();

        $player = $stats['percentageGroups']['>=80'][0];
        $this->assertArrayHasKey('name', $player);
        $this->assertArrayHasKey('playable_class', $player);
        $this->assertSame('Arthas', $player['name']);
    }

    // ==================== stats: totalPlayers / totalMains / totalLinkedCharacters ====================

    #[Test]
    public function stats_counts_mains_and_linked_characters_separately(): void
    {
        $rank = $this->makeRank();
        $main1 = Character::factory()->main()->create(['rank_id' => $rank->id]);
        $main2 = Character::factory()->main()->create(['rank_id' => $rank->id]);
        $linked = Character::factory()->create(['is_main' => false, 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $report = $this->makeReport($tag, now()->subDays(1));
        $this->attachCharacter($report, $main1, 1);
        $this->attachCharacter($report, $main2, 1);
        $this->attachCharacter($report, $linked, 1);

        $stats = $this->makeDashboard()->stats();

        $this->assertSame(3, $stats['totalPlayers']);
        $this->assertSame(2, $stats['totalMains']);
        $this->assertSame(1, $stats['totalLinkedCharacters']);
    }

    // ==================== stats: droppingOff / pickingUp ====================

    #[Test]
    public function stats_identifies_dropping_off_player(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->main()->create(['name' => 'Vashj', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        // 4 older raids attended
        for ($i = 0; $i < 4; $i++) {
            $report = $this->makeReport($tag, now()->subDays(20 + $i));
            $this->attachCharacter($report, $character, 1);
        }

        // 4 recent raids not attended
        for ($i = 0; $i < 4; $i++) {
            $this->makeReport($tag, now()->subDays(1 + $i));
        }

        $stats = $this->makeDashboard()->stats();

        $this->assertCount(1, $stats['droppingOff']);
        $this->assertSame('Vashj', $stats['droppingOff'][0]['name']);
    }

    #[Test]
    public function stats_identifies_picking_up_player(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->main()->create(['name' => 'Maiev', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        // One old debut raid
        $debut = $this->makeReport($tag, now()->subDays(30));
        $this->attachCharacter($debut, $character, 1);

        // 3 mid raids not attended
        for ($i = 0; $i < 3; $i++) {
            $this->makeReport($tag, now()->subDays(15 + $i));
        }

        // 4 recent raids attended
        for ($i = 0; $i < 4; $i++) {
            $report = $this->makeReport($tag, now()->subDays(1 + $i));
            $this->attachCharacter($report, $character, 1);
        }

        $stats = $this->makeDashboard()->stats();

        $this->assertCount(1, $stats['pickingUp']);
        $this->assertSame('Maiev', $stats['pickingUp'][0]['name']);
    }

    #[Test]
    public function stats_dropping_off_is_empty_when_no_data(): void
    {
        $stats = $this->makeDashboard()->stats();

        $this->assertEmpty($stats['droppingOff']);
    }

    #[Test]
    public function stats_picking_up_is_empty_when_no_data(): void
    {
        $stats = $this->makeDashboard()->stats();

        $this->assertEmpty($stats['pickingUp']);
    }

    // ==================== stats: phaseAttendance / previousPhaseAttendance ====================

    #[Test]
    public function stats_phase_attendance_is_null_when_no_phases_exist(): void
    {
        $stats = $this->makeDashboard()->stats();

        $this->assertNull($stats['phaseAttendance']);
        $this->assertNull($stats['previousPhaseAttendance']);
    }

    #[Test]
    public function stats_phase_attendance_only_counts_reports_since_current_phase_start(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->main()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        Phase::factory()->create(['start_date' => now()->subDays(14)]);

        // Pre-phase report (attended) — should be excluded
        $pre = $this->makeReport($tag, now()->subDays(20));
        $this->attachCharacter($pre, $character, 1);

        // Post-phase report (attended)
        $post = $this->makeReport($tag, now()->subDays(10));
        $this->attachCharacter($post, $character, 1);

        // Post-phase report (not attended)
        $this->makeReport($tag, now()->subDays(5));

        $stats = $this->makeDashboard()->stats();

        $this->assertSame(50.0, $stats['phaseAttendance']);
    }

    #[Test]
    public function stats_previous_phase_attendance_is_bounded_between_phase_starts(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->main()->create(['name' => 'Vol\'jin', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        Phase::factory()->create(['number' => 1, 'start_date' => now()->subDays(30)]);
        Phase::factory()->create(['number' => 2, 'start_date' => now()->subDays(7)]);

        // Phase 1 report (attended)
        $phase1Report = $this->makeReport($tag, now()->subDays(20));
        $this->attachCharacter($phase1Report, $character, 1);

        // Phase 2 report (not attended)
        $this->makeReport($tag, now()->subDays(3));

        $stats = $this->makeDashboard()->stats();

        $this->assertSame(100.0, $stats['previousPhaseAttendance']);
        $this->assertNull($stats['phaseAttendance']);
    }

    #[Test]
    public function stats_previous_phase_attendance_is_null_when_only_one_phase(): void
    {
        Phase::factory()->create(['start_date' => now()->subDays(14)]);

        $stats = $this->makeDashboard()->stats();

        $this->assertNull($stats['previousPhaseAttendance']);
    }

    // ==================== stats: benchedLastWeek ====================

    #[Test]
    public function stats_benched_last_week_is_empty_when_no_recent_reports(): void
    {
        $stats = $this->makeDashboard()->stats();

        $this->assertEmpty($stats['benchedLastWeek']);
    }

    #[Test]
    public function stats_benched_last_week_groups_by_guild_tag_name(): void
    {
        $rank = $this->makeRank();
        $kael = Character::factory()->main()->create(['name' => 'Kael', 'rank_id' => $rank->id]);
        $illidan = Character::factory()->main()->create(['name' => 'Illidan', 'rank_id' => $rank->id]);

        $monday = GuildTag::factory()->countsAttendance()->withoutPhase()->create(['name' => 'Monday']);
        $tuesday = GuildTag::factory()->countsAttendance()->withoutPhase()->create(['name' => 'Tuesday']);

        $mondayReport = $this->makeReport($monday, now()->subDays(2));
        $this->attachCharacter($mondayReport, $kael, 2);

        $tuesdayReport = $this->makeReport($tuesday, now()->subDays(1));
        $this->attachCharacter($tuesdayReport, $illidan, 2);

        $stats = $this->makeDashboard()->stats();

        $this->assertArrayHasKey('Monday', $stats['benchedLastWeek']);
        $this->assertArrayHasKey('Tuesday', $stats['benchedLastWeek']);
        $this->assertSame('Kael', $stats['benchedLastWeek']['Monday'][0]['name']);
        $this->assertSame('Illidan', $stats['benchedLastWeek']['Tuesday'][0]['name']);
    }

    #[Test]
    public function stats_benched_last_week_excludes_reports_older_than_seven_days(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->main()->create(['name' => 'Illidan', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $old = $this->makeReport($tag, now()->subDays(10));
        $this->attachCharacter($old, $character, 2);

        $stats = $this->makeDashboard()->stats();

        $this->assertEmpty($stats['benchedLastWeek']);
    }

    // ==================== upcomingAbsences ====================

    #[Test]
    public function upcoming_absences_returns_empty_collection_when_none_exist(): void
    {
        $absences = $this->makeDashboard()->upcomingAbsences();

        $this->assertCount(0, $absences);
    }

    #[Test]
    public function upcoming_absences_returns_future_absences_ordered_by_start_date(): void
    {
        $rank = $this->makeRank();
        $characterA = Character::factory()->main()->create(['name' => 'Arthas', 'rank_id' => $rank->id]);
        $characterB = Character::factory()->main()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);

        PlannedAbsence::factory()->create([
            'character_id' => $characterB->id,
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(12),
        ]);
        PlannedAbsence::factory()->create([
            'character_id' => $characterA->id,
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(4),
        ]);

        $absences = $this->makeDashboard()->upcomingAbsences();

        $this->assertCount(2, $absences);
        $this->assertSame('Arthas', $absences[0]->character->name);
        $this->assertSame('Jaina', $absences[1]->character->name);
    }

    #[Test]
    public function upcoming_absences_excludes_past_absences(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->main()->create(['rank_id' => $rank->id]);

        PlannedAbsence::factory()->create([
            'character_id' => $character->id,
            'start_date' => now()->subDays(5),
            'end_date' => now()->subDays(3),
        ]);

        $absences = $this->makeDashboard()->upcomingAbsences();

        $this->assertCount(0, $absences);
    }

    #[Test]
    public function upcoming_absences_is_limited_to_four(): void
    {
        $rank = $this->makeRank();

        for ($i = 1; $i <= 6; $i++) {
            $character = Character::factory()->main()->create(['rank_id' => $rank->id]);
            PlannedAbsence::factory()->create([
                'character_id' => $character->id,
                'start_date' => now()->addDays($i),
                'end_date' => now()->addDays($i + 1),
            ]);
        }

        $absences = $this->makeDashboard()->upcomingAbsences();

        $this->assertCount(4, $absences);
    }

    #[Test]
    public function upcoming_absences_eager_loads_character_relation(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->main()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);

        PlannedAbsence::factory()->create([
            'character_id' => $character->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
        ]);

        $absences = $this->makeDashboard()->upcomingAbsences();

        $this->assertTrue($absences[0]->relationLoaded('character'));
        $this->assertSame('Thrall', $absences[0]->character->name);
    }
}
