<?php

namespace Tests\Unit\Services\AttendanceCalculator;

use App\Exceptions\EmptyCollectionException;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Report;
use App\Services\AttendanceCalculator\AttendanceCalculator;
use App\Services\AttendanceCalculator\AttendanceMatrix;
use App\Services\AttendanceCalculator\AttendanceMatrixFilters;
use App\Services\AttendanceCalculator\CharacterAttendanceStats;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AttendanceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Europe/Paris']);
    }

    protected function makeCalculator(): AttendanceCalculator
    {
        return new AttendanceCalculator;
    }

    protected function makeMatrix(): AttendanceMatrix
    {
        return new AttendanceMatrix($this->makeCalculator());
    }

    protected function makeRank(bool $countsAttendance = true): GuildRank
    {
        return $countsAttendance
            ? GuildRank::factory()->create()
            : GuildRank::factory()->doesNotCountAttendance()->create();
    }

    protected function makeTag(bool $countsAttendance = true): GuildTag
    {
        return $countsAttendance
            ? GuildTag::factory()->countsAttendance()->withoutPhase()->create()
            : GuildTag::factory()->doesNotCountAttendance()->withoutPhase()->create();
    }

    protected function makeReport(GuildTag $tag, Carbon $startTime): Report
    {
        return Report::factory()->withGuildTag($tag)->create(['start_time' => $startTime]);
    }

    protected function attachCharacter(Report $report, Character $character, int $presence): void
    {
        $report->characters()->attach($character->id, ['presence' => $presence]);
    }

    // ==================== wholeGuild: Empty Input Tests ====================

    public function test_calculate_returns_empty_collection_when_no_counting_ranks_exist(): void
    {
        GuildRank::factory()->doesNotCountAttendance()->create();

        $result = $this->makeCalculator()->wholeGuild();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_calculate_returns_empty_collection_when_no_qualifying_reports_exist(): void
    {
        $rank = $this->makeRank();
        Character::factory()->create(['rank_id' => $rank->id]);
        $this->makeTag(false); // non-counting tag — no qualifying reports

        $result = $this->makeCalculator()->wholeGuild();

        $this->assertTrue($result->isEmpty());
    }

    // ==================== wholeGuild: Presence Filtering Tests ====================

    public function test_calculate_does_not_count_presence_zero_as_attendance(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $this->attachCharacter($report1, $character, 1);
        $this->attachCharacter($report2, $character, 0);

        $thrall = $this->makeCalculator()->wholeGuild()->firstWhere('name', 'Thrall');

        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(1, $thrall->reportsAttended);
        $this->assertEquals(50.0, $thrall->percentage);
    }

    public function test_calculate_does_not_count_presence_outside_1_and_2(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $this->attachCharacter($report1, $character, 1);
        $this->attachCharacter($report2, $character, 3);

        $thrall = $this->makeCalculator()->wholeGuild()->firstWhere('name', 'Thrall');

        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(1, $thrall->reportsAttended);
        $this->assertEquals(50.0, $thrall->percentage);
    }

    public function test_calculate_counts_both_presence_1_and_2_as_attended(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $report3 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));
        $this->attachCharacter($report1, $character, 1);
        $this->attachCharacter($report2, $character, 2);
        $this->attachCharacter($report3, $character, 0);

        $thrall = $this->makeCalculator()->wholeGuild()->firstWhere('name', 'Thrall');

        $this->assertEquals(3, $thrall->totalReports);
        $this->assertEquals(2, $thrall->reportsAttended);
        $this->assertEquals(66.67, $thrall->percentage);
    }

    public function test_calculate_player_with_only_invalid_presence_has_zero_percent(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $this->attachCharacter($report1, $character, 0);
        $this->attachCharacter($report2, $character, 3);

        $thrall = $this->makeCalculator()->wholeGuild()->firstWhere('name', 'Thrall');

        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(0, $thrall->reportsAttended);
        $this->assertEquals(0.0, $thrall->percentage);
    }

    // ==================== wholeGuild: Result Sorting Tests ====================

    public function test_calculate_results_are_sorted_alphabetically_by_name(): void
    {
        $rank = $this->makeRank();
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));

        foreach (['Zara', 'Alice', 'Milo'] as $name) {
            $character = Character::factory()->create(['name' => $name, 'rank_id' => $rank->id]);
            $this->attachCharacter($report, $character, 1);
        }

        $stats = $this->makeCalculator()->wholeGuild();

        $this->assertEquals('Alice', $stats[0]->name);
        $this->assertEquals('Milo', $stats[1]->name);
        $this->assertEquals('Zara', $stats[2]->name);
    }

    // ==================== wholeGuild: Absent Player Tests ====================

    public function test_calculate_player_absent_after_first_appearance(): void
    {
        $rank = $this->makeRank();
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $report3 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));

        $this->attachCharacter($report1, $jaina, 1);
        $this->attachCharacter($report1, $thrall, 1);
        $this->attachCharacter($report2, $thrall, 1);
        $this->attachCharacter($report3, $thrall, 1);

        $jainaStats = $this->makeCalculator()->wholeGuild()->firstWhere('name', 'Jaina');

        $this->assertEquals(3, $jainaStats->totalReports);
        $this->assertEquals(1, $jainaStats->reportsAttended);
        $this->assertEquals(33.33, $jainaStats->percentage);
    }

    public function test_calculate_single_player_single_report(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 1);

        $thrall = $this->makeCalculator()->wholeGuild()->first();

        $this->assertEquals(1, $thrall->totalReports);
        $this->assertEquals(1, $thrall->reportsAttended);
        $this->assertEquals(100.0, $thrall->percentage);
    }

    // ==================== wholeGuild: First Attendance Date Tests ====================

    public function test_calculate_returns_correct_first_attendance_date(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $jan08 = Carbon::parse('2025-01-08 20:00', 'Europe/Paris');
        $jan15 = Carbon::parse('2025-01-15 20:00', 'Europe/Paris');

        // Insert jan15 first — first attendance should still resolve to jan08
        $report1 = $this->makeReport($tag, $jan15);
        $report2 = $this->makeReport($tag, $jan08);
        $this->attachCharacter($report1, $character, 1);
        $this->attachCharacter($report2, $character, 1);

        $thrall = $this->makeCalculator()->wholeGuild()->firstWhere('name', 'Thrall');

        $this->assertTrue($thrall->firstAttendance->eq($jan08));
    }

    public function test_calculate_tracks_first_attendance_per_player(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $jan01 = Carbon::parse('2025-01-01 20:00', 'Europe/Paris');
        $jan08 = Carbon::parse('2025-01-08 20:00', 'Europe/Paris');

        $report1 = $this->makeReport($tag, $jan01);
        $report2 = $this->makeReport($tag, $jan08);

        $this->attachCharacter($report1, $thrall, 1);
        $this->attachCharacter($report2, $thrall, 1);
        $this->attachCharacter($report2, $jaina, 1);

        $stats = $this->makeCalculator()->wholeGuild();

        $thrallStats = $stats->firstWhere('name', 'Thrall');
        $jainaStats = $stats->firstWhere('name', 'Jaina');

        $this->assertTrue($thrallStats->firstAttendance->eq($jan01));
        $this->assertTrue($jainaStats->firstAttendance->eq($jan08));

        // Jaina joined on report 2, so she only has 1 total report
        $this->assertEquals(1, $jainaStats->totalReports);
        // Thrall was there from report 1, so he has 2 total reports
        $this->assertEquals(2, $thrallStats->totalReports);
    }

    // ==================== wholeGuild: Character ID Tests ====================

    public function test_calculate_includes_character_id_in_stats(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 1);

        $thrall = $this->makeCalculator()->wholeGuild()->first();

        $this->assertEquals($character->id, $thrall->id);
    }

    // ==================== wholeGuild: Multiple Players Tests ====================

    public function test_calculate_handles_multiple_players(): void
    {
        $rank = $this->makeRank();
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));

        foreach ([['name' => 'Thrall', 'presence' => 1], ['name' => 'Jaina', 'presence' => 1], ['name' => 'Sylvanas', 'presence' => 2]] as $data) {
            $character = Character::factory()->create(['name' => $data['name'], 'rank_id' => $rank->id]);
            $this->attachCharacter($report, $character, $data['presence']);
        }

        $stats = $this->makeCalculator()->wholeGuild();

        $this->assertCount(3, $stats);
        $this->assertNotNull($stats->firstWhere('name', 'Thrall'));
        $this->assertNotNull($stats->firstWhere('name', 'Jaina'));
        $this->assertNotNull($stats->firstWhere('name', 'Sylvanas'));
    }

    // ==================== wholeGuild: Returns CharacterAttendanceStats Tests ====================

    public function test_calculate_returns_character_attendance_stats_objects(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 1);

        $stats = $this->makeCalculator()->wholeGuild();

        $this->assertInstanceOf(CharacterAttendanceStats::class, $stats->first());
    }

    // ==================== wholeGuild: Rank Filtering Tests ====================

    public function test_calculate_excludes_characters_from_non_counting_ranks(): void
    {
        $countingRank = $this->makeRank(true);
        $nonCountingRank = $this->makeRank(false);
        $countingChar = Character::factory()->create(['name' => 'CountingPlayer', 'rank_id' => $countingRank->id]);
        $nonCountingChar = Character::factory()->create(['name' => 'NonCountingPlayer', 'rank_id' => $nonCountingRank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $countingChar, 1);
        $this->attachCharacter($report, $nonCountingChar, 1);

        $stats = $this->makeCalculator()->wholeGuild();

        $names = $stats->pluck('name')->toArray();
        $this->assertContains('CountingPlayer', $names);
        $this->assertNotContains('NonCountingPlayer', $names);
    }

    public function test_calculate_excludes_reports_from_non_counting_guild_tags(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $countingTag = $this->makeTag(true);
        $nonCountingTag = $this->makeTag(false);

        $countingReport = $this->makeReport($countingTag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));
        $nonCountingReport = $this->makeReport($nonCountingTag, Carbon::parse('2025-01-22 20:00', 'Europe/Paris'));
        $this->attachCharacter($countingReport, $character, 1);
        $this->attachCharacter($nonCountingReport, $character, 1);

        $thrall = $this->makeCalculator()->wholeGuild()->firstWhere('name', 'Thrall');

        // Only the one counting report should be included
        $this->assertEquals(1, $thrall->totalReports);
    }

    // ==================== wholeGuild: Same-Day Raid Merging Tests ====================

    public function test_calculate_merges_same_day_raids_into_single_record(): void
    {
        $rank = $this->makeRank();
        $fizzywigs = Character::factory()->create(['name' => 'Fizzywigs', 'rank_id' => $rank->id]);
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        // Two raids on the same evening — Fizzywigs in one, not the other
        $raid1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $raid2 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));

        $this->attachCharacter($raid1, $fizzywigs, 1);
        $this->attachCharacter($raid1, $thrall, 1);
        $this->attachCharacter($raid2, $thrall, 1);
        $this->attachCharacter($raid2, $jaina, 1);

        $stats = $this->makeCalculator()->wholeGuild();

        // Should be 1 total report (merged day), all players attended
        $this->assertEquals(1, $stats->firstWhere('name', 'Fizzywigs')->totalReports);
        $this->assertEquals(1, $stats->firstWhere('name', 'Fizzywigs')->reportsAttended);
        $this->assertEquals(100.0, $stats->firstWhere('name', 'Fizzywigs')->percentage);

        $this->assertEquals(1, $stats->firstWhere('name', 'Thrall')->totalReports);
        $this->assertEquals(1, $stats->firstWhere('name', 'Jaina')->totalReports);
    }

    public function test_calculate_raid_before_0500_belongs_to_previous_day(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        // 03:00 on Jan 16 should be part of the Jan 15 raid day
        $raid1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $raid2 = $this->makeReport($tag, Carbon::parse('2025-01-16 03:00', 'Europe/Paris'));

        $this->attachCharacter($raid1, $thrall, 1);
        $this->attachCharacter($raid2, $jaina, 1);

        $stats = $this->makeCalculator()->wholeGuild();

        // Both raids merge into one raid day
        $this->assertEquals(1, $stats->firstWhere('name', 'Thrall')->totalReports);
        $this->assertEquals(1, $stats->firstWhere('name', 'Jaina')->totalReports);
    }

    public function test_calculate_raid_after_0500_belongs_to_current_day(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        // 06:00 on Jan 16 should be a new raid day, separate from Jan 15
        $raid1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $raid2 = $this->makeReport($tag, Carbon::parse('2025-01-16 06:00', 'Europe/Paris'));

        $this->attachCharacter($raid1, $thrall, 1);
        $this->attachCharacter($raid2, $thrall, 1);

        $stats = $this->makeCalculator()->wholeGuild();

        // Two separate raid days
        $this->assertEquals(2, $stats->firstWhere('name', 'Thrall')->totalReports);
    }

    public function test_calculate_different_days_remain_separate(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $raid1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $raid2 = $this->makeReport($tag, Carbon::parse('2025-01-22 19:00', 'Europe/Paris'));

        $this->attachCharacter($raid1, $thrall, 1);
        $this->attachCharacter($raid2, $thrall, 1);

        $stats = $this->makeCalculator()->wholeGuild();

        $this->assertEquals(2, $stats->firstWhere('name', 'Thrall')->totalReports);
        $this->assertEquals(2, $stats->firstWhere('name', 'Thrall')->reportsAttended);
    }

    public function test_calculate_merge_keeps_best_presence_value(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        // Presence 0 in one raid, presence 1 in another — should count as attended
        $raid1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $raid2 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));

        $this->attachCharacter($raid1, $thrall, 0);
        $this->attachCharacter($raid2, $thrall, 1);

        $stats = $this->makeCalculator()->wholeGuild();

        $this->assertEquals(1, $stats->firstWhere('name', 'Thrall')->reportsAttended);
        $this->assertEquals(100.0, $stats->firstWhere('name', 'Thrall')->percentage);
    }

    public function test_calculate_merge_prefers_present_over_benched(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        // Presence 1 (present) should be preferred over 2 (benched)
        $raid1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $raid2 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));

        $this->attachCharacter($raid1, $thrall, 2);
        $this->attachCharacter($raid2, $thrall, 1);

        $stats = $this->makeCalculator()->wholeGuild();

        // Both 1 and 2 count as attended, so either way it's 100%
        $this->assertEquals(1, $stats->firstWhere('name', 'Thrall')->reportsAttended);
        $this->assertEquals(100.0, $stats->firstWhere('name', 'Thrall')->percentage);
    }

    public function test_calculate_merge_three_raids_same_day(): void
    {
        $rank = $this->makeRank();
        $alice = Character::factory()->create(['name' => 'Alice', 'rank_id' => $rank->id]);
        $bob = Character::factory()->create(['name' => 'Bob', 'rank_id' => $rank->id]);
        $charlie = Character::factory()->create(['name' => 'Charlie', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $raid1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $raid2 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:30', 'Europe/Paris'));
        $raid3 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));

        $this->attachCharacter($raid1, $alice, 1);
        $this->attachCharacter($raid2, $bob, 1);
        $this->attachCharacter($raid3, $charlie, 1);

        $stats = $this->makeCalculator()->wholeGuild();

        // All three raids merge into one day — 3 players, 1 report each
        $this->assertCount(3, $stats);
        $this->assertEquals(1, $stats->firstWhere('name', 'Alice')->totalReports);
        $this->assertEquals(1, $stats->firstWhere('name', 'Bob')->totalReports);
        $this->assertEquals(1, $stats->firstWhere('name', 'Charlie')->totalReports);
    }

    // ==================== forRanks: Tests ====================

    public function test_for_ranks_throws_for_empty_collection(): void
    {
        $this->expectException(EmptyCollectionException::class);

        $this->makeCalculator()->forRanks(collect());
    }

    public function test_for_ranks_returns_stats_for_characters_in_specified_ranks(): void
    {
        $rank1 = $this->makeRank();
        $rank2 = $this->makeRank();
        $char1 = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank1->id]);
        $char2 = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank2->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $char1, 1);
        $this->attachCharacter($report, $char2, 1);

        // Only query rank1 — only Thrall should appear
        $stats = $this->makeCalculator()->forRanks(collect([$rank1]));

        $names = $stats->pluck('name')->toArray();
        $this->assertContains('Thrall', $names);
        $this->assertNotContains('Jaina', $names);
    }

    public function test_for_ranks_returns_empty_when_no_qualifying_reports_for_those_ranks(): void
    {
        $rank = $this->makeRank();
        Character::factory()->create(['rank_id' => $rank->id]);
        $this->makeTag(false); // non-counting tag — no qualifying reports

        $stats = $this->makeCalculator()->forRanks(collect([$rank]));

        $this->assertTrue($stats->isEmpty());
    }

    public function test_for_ranks_returns_character_attendance_stats_objects(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 1);

        $stats = $this->makeCalculator()->forRanks(collect([$rank]));

        $this->assertInstanceOf(CharacterAttendanceStats::class, $stats->first());
    }

    // ==================== forCharacter: Tests ====================

    public function test_for_character_returns_empty_when_no_counting_reports_exist(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['rank_id' => $rank->id]);

        $stats = $this->makeCalculator()->forCharacter($character);

        $this->assertTrue($stats->isEmpty());
    }

    public function test_for_character_returns_only_the_specified_character(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $thrall, 1);
        $this->attachCharacter($report, $jaina, 1);

        $stats = $this->makeCalculator()->forCharacter($thrall);

        $this->assertCount(1, $stats);
        $this->assertEquals('Thrall', $stats->first()->name);
    }

    public function test_for_character_returns_empty_when_character_is_in_non_counting_rank(): void
    {
        $nonCountingRank = $this->makeRank(false);
        $character = Character::factory()->create(['rank_id' => $nonCountingRank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 1);

        $stats = $this->makeCalculator()->forCharacter($character);

        $this->assertTrue($stats->isEmpty());
    }

    public function test_for_character_calculates_lifetime_stats_from_first_attendance(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $this->attachCharacter($report1, $character, 1);
        $this->attachCharacter($report2, $character, 1);

        $thrall = $this->makeCalculator()->forCharacter($character)->first();

        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(2, $thrall->reportsAttended);
        $this->assertEquals(100.0, $thrall->percentage);
    }

    // ==================== forReport: Tests ====================

    public function test_for_report_returns_empty_when_guild_tag_does_not_count_attendance(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['rank_id' => $rank->id]);
        $tag = $this->makeTag(false);
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 1);

        $stats = $this->makeCalculator()->forReport($report);

        $this->assertTrue($stats->isEmpty());
    }

    public function test_for_report_returns_stats_for_characters_in_the_report(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $thrall, 1);
        $this->attachCharacter($report, $jaina, 1);

        $stats = $this->makeCalculator()->forReport($report);

        $this->assertCount(2, $stats);
        $this->assertNotNull($stats->firstWhere('name', 'Thrall'));
        $this->assertNotNull($stats->firstWhere('name', 'Jaina'));
    }

    public function test_for_report_excludes_characters_in_non_counting_ranks(): void
    {
        $countingRank = $this->makeRank(true);
        $nonCountingRank = $this->makeRank(false);
        $countingChar = Character::factory()->create(['name' => 'CountingPlayer', 'rank_id' => $countingRank->id]);
        $nonCountingChar = Character::factory()->create(['name' => 'NonCountingPlayer', 'rank_id' => $nonCountingRank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $countingChar, 1);
        $this->attachCharacter($report, $nonCountingChar, 1);

        $stats = $this->makeCalculator()->forReport($report);

        $names = $stats->pluck('name')->toArray();
        $this->assertContains('CountingPlayer', $names);
        $this->assertNotContains('NonCountingPlayer', $names);
    }

    public function test_for_report_counts_only_the_single_report(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $thrall, 1);
        $this->attachCharacter($report, $jaina, 2);

        $stats = $this->makeCalculator()->forReport($report);

        $this->assertEquals(1, $stats->firstWhere('name', 'Thrall')->totalReports);
        $this->assertEquals(1, $stats->firstWhere('name', 'Thrall')->reportsAttended);
        $this->assertEquals(1, $stats->firstWhere('name', 'Jaina')->totalReports);
        $this->assertEquals(1, $stats->firstWhere('name', 'Jaina')->reportsAttended);
    }

    // ==================== matrixForWholeGuild: Return Type Tests ====================

    public function test_matrix_for_whole_guild_returns_attendance_matrix_instance(): void
    {
        $matrix = $this->makeMatrix()->matrixForWholeGuild();

        $this->assertInstanceOf(AttendanceMatrix::class, $matrix);
    }

    // ==================== matrixForWholeGuild: Empty Input Tests ====================

    public function test_matrix_returns_empty_when_no_counting_ranks_exist(): void
    {
        GuildRank::factory()->doesNotCountAttendance()->create();

        $matrix = $this->makeMatrix()->matrixForWholeGuild();

        $this->assertEmpty($matrix->raids);
        $this->assertEmpty($matrix->rows);
    }

    public function test_matrix_returns_empty_when_no_qualifying_reports_exist(): void
    {
        $rank = $this->makeRank();
        Character::factory()->create(['rank_id' => $rank->id]);
        $this->makeTag(false);

        $matrix = $this->makeMatrix()->matrixForWholeGuild();

        $this->assertEmpty($matrix->raids);
        $this->assertEmpty($matrix->rows);
    }

    // ==================== matrixForWholeGuild: Column Tests ====================

    public function test_matrix_columns_are_in_reverse_chronological_order(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report1, $character, 1);
        $this->attachCharacter($report2, $character, 1);

        $matrix = $this->makeMatrix()->matrixForWholeGuild();

        // Most recent raid is raids[0], oldest is last
        $this->assertEquals('08/01', $matrix->raids[0]['date']);
        $this->assertEquals('01/01', $matrix->raids[1]['date']);
    }

    public function test_matrix_raid_date_is_formatted_as_dd_mm(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-03-05 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 1);

        $matrix = $this->makeMatrix()->matrixForWholeGuild();

        $this->assertEquals('05/03', $matrix->raids[0]['date']);
    }

    public function test_matrix_raid_column_includes_day_of_week(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['rank_id' => $rank->id]);
        $tag = $this->makeTag();
        // 2025-01-01 is a Wednesday
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 1);

        $matrix = $this->makeMatrix()->matrixForWholeGuild();

        $this->assertEquals('Wed', $matrix->raids[0]['dayOfWeek']);
    }

    // ==================== matrixForWholeGuild: Row Tests ====================

    public function test_matrix_rows_are_sorted_alphabetically_by_name(): void
    {
        $rank = $this->makeRank();
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));

        foreach (['Zara', 'Alice', 'Milo'] as $name) {
            $character = Character::factory()->create(['name' => $name, 'rank_id' => $rank->id]);
            $this->attachCharacter($report, $character, 1);
        }

        $matrix = $this->makeMatrix()->matrixForWholeGuild();

        $this->assertEquals('Alice', $matrix->rows[0]['name']);
        $this->assertEquals('Milo', $matrix->rows[1]['name']);
        $this->assertEquals('Zara', $matrix->rows[2]['name']);
    }

    public function test_matrix_row_percentage_is_calculated_correctly(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $report3 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));
        $this->attachCharacter($report1, $character, 1);
        $this->attachCharacter($report3, $character, 1);

        $matrix = $this->makeMatrix()->matrixForWholeGuild();
        $row = collect($matrix->rows)->firstWhere('name', 'Thrall');

        $this->assertEquals(66.67, $row['percentage']);
    }

    // ==================== matrixForWholeGuild: Cell Value Tests ====================

    public function test_matrix_cells_before_first_attendance_are_null(): void
    {
        $rank = $this->makeRank();
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        // Thrall attends from raid 1; Jaina only joins on raid 2
        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $this->attachCharacter($report1, $thrall, 1);
        $this->attachCharacter($report2, $thrall, 1);
        $this->attachCharacter($report2, $jaina, 1);

        $matrix = $this->makeMatrix()->matrixForWholeGuild();
        $jainaRow = collect($matrix->rows)->firstWhere('name', 'Jaina');

        // Columns are newest-first: raids[0]=Jan 8, raids[1]=Jan 1
        // attendance[0] = Jan 8 = Jaina's first raid — should be 1
        $this->assertEquals(1, $jainaRow['attendance'][0]);
        // attendance[1] = Jan 1 = before Jaina's first attendance — should be null
        $this->assertNull($jainaRow['attendance'][1]);
    }

    public function test_matrix_absent_cell_after_first_attendance_is_zero(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $this->attachCharacter($report1, $character, 1);
        // Thrall is absent from report 2

        $matrix = $this->makeMatrix()->matrixForWholeGuild();
        $row = collect($matrix->rows)->firstWhere('name', 'Thrall');

        // Columns are newest-first: raids[0]=Jan 8 (absent), raids[1]=Jan 1 (present)
        $this->assertEquals(0, $row['attendance'][0]);
        $this->assertEquals(1, $row['attendance'][1]);
    }

    public function test_matrix_presence_1_cell_is_one(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 1);

        $matrix = $this->makeMatrix()->matrixForWholeGuild();
        $row = collect($matrix->rows)->firstWhere('name', 'Thrall');

        $this->assertEquals(1, $row['attendance'][0]);
    }

    public function test_matrix_presence_2_cell_is_two(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 2);

        $matrix = $this->makeMatrix()->matrixForWholeGuild();
        $row = collect($matrix->rows)->firstWhere('name', 'Thrall');

        $this->assertEquals(2, $row['attendance'][0]);
    }

    public function test_matrix_same_day_raids_are_merged_into_one_column(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        // Two raids on the same evening — one column should appear
        $raid1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $raid2 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));
        $this->attachCharacter($raid1, $thrall, 1);
        $this->attachCharacter($raid2, $jaina, 1);

        $matrix = $this->makeMatrix()->matrixForWholeGuild();

        $this->assertCount(1, $matrix->raids);
        $thrallRow = collect($matrix->rows)->firstWhere('name', 'Thrall');
        $jainaRow = collect($matrix->rows)->firstWhere('name', 'Jaina');
        $this->assertEquals(1, $thrallRow['attendance'][0]);
        $this->assertEquals(1, $jainaRow['attendance'][0]);
    }

    public function test_matrix_to_array_returns_correct_structure(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 1);

        $matrix = $this->makeMatrix()->matrixForWholeGuild();
        $array = $matrix->toArray();

        $this->assertArrayHasKey('raids', $array);
        $this->assertArrayHasKey('rows', $array);
        $this->assertArrayHasKey('code', $array['raids'][0]);
        $this->assertArrayHasKey('dayOfWeek', $array['raids'][0]);
        $this->assertArrayHasKey('date', $array['raids'][0]);
        $this->assertArrayHasKey('name', $array['rows'][0]);
        $this->assertArrayHasKey('percentage', $array['rows'][0]);
        $this->assertArrayHasKey('attendance', $array['rows'][0]);
        $this->assertArrayHasKey('playable_class', $array['rows'][0]);
    }

    // ==================== matrixWithFilters: Row Field Tests ====================

    public function test_matrix_rows_include_id_and_rank_id(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 1);

        $matrix = $this->makeMatrix()->matrixWithFilters(new AttendanceMatrixFilters);
        $row = collect($matrix->rows)->firstWhere('name', 'Thrall');

        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('rank_id', $row);
        $this->assertEquals($character->id, $row['id']);
        $this->assertEquals($rank->id, $row['rank_id']);
    }

    // ==================== matrixWithFilters: Guild Tag Filter Tests ====================

    public function test_matrix_with_filters_guild_tag_filter_includes_only_selected_tag(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag1 = $this->makeTag();
        $tag2 = $this->makeTag();

        $report1 = $this->makeReport($tag1, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag2, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $this->attachCharacter($report1, $thrall, 1);
        $this->attachCharacter($report2, $jaina, 1);

        $matrix = $this->makeMatrix()->matrixWithFilters(new AttendanceMatrixFilters(
            guildTagIds: [$tag1->id],
        ));

        $names = collect($matrix->rows)->pluck('name');
        $this->assertContains('Thrall', $names);
        $this->assertNotContains('Jaina', $names);
    }

    // ==================== matrixWithFilters: Zone Filter Tests ====================

    public function test_matrix_with_filters_zone_filter_includes_only_reports_for_that_zone(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $report1 = Report::factory()->withGuildTag($tag)->withZone(1001, 'Black Temple')->create(['start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris')]);
        $report2 = Report::factory()->withGuildTag($tag)->withZone(1002, 'Sunwell Plateau')->create(['start_time' => Carbon::parse('2025-01-08 20:00', 'Europe/Paris')]);
        $this->attachCharacter($report1, $thrall, 1);
        $this->attachCharacter($report2, $jaina, 1);

        $matrix = $this->makeMatrix()->matrixWithFilters(new AttendanceMatrixFilters(
            zoneIds: [1001],
        ));

        $names = collect($matrix->rows)->pluck('name');
        $this->assertContains('Thrall', $names);
        $this->assertNotContains('Jaina', $names);
    }

    // ==================== matrixWithFilters: Date Filter Tests ====================

    public function test_matrix_with_filters_since_date_excludes_older_reports(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $oldReport = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $newReport = $this->makeReport($tag, Carbon::parse('2025-02-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($oldReport, $thrall, 1);
        $this->attachCharacter($newReport, $jaina, 1);

        // sinceDate = Jan 15 at 05:00 UTC, excludes Jan 1 report
        $sinceDate = Carbon::parse('2025-01-15 05:00:00', 'UTC');

        $matrix = $this->makeMatrix()->matrixWithFilters(new AttendanceMatrixFilters(
            sinceDate: $sinceDate,
        ));

        $names = collect($matrix->rows)->pluck('name');
        $this->assertNotContains('Thrall', $names);
        $this->assertContains('Jaina', $names);
    }

    public function test_matrix_with_filters_before_date_excludes_newer_reports(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $oldReport = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $newReport = $this->makeReport($tag, Carbon::parse('2025-02-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($oldReport, $thrall, 1);
        $this->attachCharacter($newReport, $jaina, 1);

        // beforeDate = Jan 15 at 05:00 UTC, excludes Feb 1 report
        $beforeDate = Carbon::parse('2025-01-15 05:00:00', 'UTC');

        $matrix = $this->makeMatrix()->matrixWithFilters(new AttendanceMatrixFilters(
            beforeDate: $beforeDate,
        ));

        $names = collect($matrix->rows)->pluck('name');
        $this->assertContains('Thrall', $names);
        $this->assertNotContains('Jaina', $names);
    }
}
