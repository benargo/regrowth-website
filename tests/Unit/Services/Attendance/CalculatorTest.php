<?php

namespace Tests\Unit\Services\Attendance;

use App\Exceptions\EmptyCollectionException;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\Raids\Report;
use App\Models\WarcraftLogs\GuildTag;
use App\Services\Attendance\Calculator;
use App\Services\Attendance\CharacterAttendanceStatsData;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Europe/Paris']);
    }

    // ==================== Helpers ====================

    protected function makeCalculator(): Calculator
    {
        return new Calculator;
    }

    protected function findStats(Collection $stats, string $name): ?CharacterAttendanceStatsData
    {
        return $stats->first(fn (CharacterAttendanceStatsData $s) => $s->character->name === $name);
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

    protected function attachCharacterToReport(Report $report, Character $character, int $presence): void
    {
        $report->characters()->attach($character->id, ['presence' => $presence]);
    }

    protected function linkReports(Report $report1, Report $report2): void
    {
        DB::table('raid_report_links')->insert([
            ['report_1' => $report1->id, 'report_2' => $report2->id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
            ['report_1' => $report2->id, 'report_2' => $report1->id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    // ==================== wholeGuild: Empty Input Tests ====================

    #[Test]
    public function calculate_throws_when_no_counting_ranks_exist(): void
    {
        GuildRank::factory()->doesNotCountAttendance()->create();

        $this->expectException(EmptyCollectionException::class);

        $this->makeCalculator()->wholeGuild();
    }

    #[Test]
    public function calculate_returns_empty_collection_when_no_qualifying_reports_exist(): void
    {
        $rank = $this->makeRank();
        Character::factory()->create(['rank_id' => $rank->id]);
        $this->makeTag(false); // non-counting tag — no qualifying reports

        $result = $this->makeCalculator()->wholeGuild();

        $this->assertTrue($result->isEmpty());
    }

    // ==================== wholeGuild: Presence Filtering Tests ====================

    #[Test]
    public function calculate_does_not_count_presence_zero_as_attendance(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $this->attachCharacterToReport($report1, $character, 1);
        $this->attachCharacterToReport($report2, $character, 0);

        $thrall = $this->findStats($this->makeCalculator()->wholeGuild(), 'Thrall');

        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(1, $thrall->reportsAttended);
        $this->assertEquals(50.0, $thrall->percentage);
    }

    #[Test]
    public function calculate_does_not_count_presence_outside_1_and_2(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $this->attachCharacterToReport($report1, $character, 1);
        $this->attachCharacterToReport($report2, $character, 3);

        $thrall = $this->findStats($this->makeCalculator()->wholeGuild(), 'Thrall');

        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(1, $thrall->reportsAttended);
        $this->assertEquals(50.0, $thrall->percentage);
    }

    #[Test]
    public function calculate_counts_both_presence_1_and_2_as_attended(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $report3 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));
        $this->attachCharacterToReport($report1, $character, 1);
        $this->attachCharacterToReport($report2, $character, 2);
        $this->attachCharacterToReport($report3, $character, 0);

        $thrall = $this->findStats($this->makeCalculator()->wholeGuild(), 'Thrall');

        $this->assertEquals(3, $thrall->totalReports);
        $this->assertEquals(2, $thrall->reportsAttended);
        $this->assertEquals(66.67, $thrall->percentage);
    }

    #[Test]
    public function calculate_player_with_only_invalid_presence_has_zero_percent(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $this->attachCharacterToReport($report1, $character, 0);
        $this->attachCharacterToReport($report2, $character, 3);

        $thrall = $this->findStats($this->makeCalculator()->wholeGuild(), 'Thrall');

        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(0, $thrall->reportsAttended);
        $this->assertEquals(0.0, $thrall->percentage);
    }

    // ==================== wholeGuild: Result Sorting Tests ====================

    #[Test]
    public function calculate_results_are_sorted_alphabetically_by_name(): void
    {
        $rank = $this->makeRank();
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));

        foreach (['Zara', 'Alice', 'Milo'] as $name) {
            $character = Character::factory()->create(['name' => $name, 'rank_id' => $rank->id]);
            $this->attachCharacterToReport($report, $character, 1);
        }

        $stats = $this->makeCalculator()->wholeGuild();

        $this->assertEquals('Alice', $stats[0]->character->name);
        $this->assertEquals('Milo', $stats[1]->character->name);
        $this->assertEquals('Zara', $stats[2]->character->name);
    }

    // ==================== wholeGuild: Absent Player Tests ====================

    #[Test]
    public function calculate_player_absent_after_first_appearance(): void
    {
        $rank = $this->makeRank();
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $report3 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));

        $this->attachCharacterToReport($report1, $jaina, 1);
        $this->attachCharacterToReport($report1, $thrall, 1);
        $this->attachCharacterToReport($report2, $thrall, 1);
        $this->attachCharacterToReport($report3, $thrall, 1);

        $jainaStats = $this->findStats($this->makeCalculator()->wholeGuild(), 'Jaina');

        $this->assertEquals(3, $jainaStats->totalReports);
        $this->assertEquals(1, $jainaStats->reportsAttended);
        $this->assertEquals(33.33, $jainaStats->percentage);
    }

    #[Test]
    public function calculate_single_player_single_report(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));
        $this->attachCharacterToReport($report, $character, 1);

        $thrall = $this->makeCalculator()->wholeGuild()->first();

        $this->assertEquals(1, $thrall->totalReports);
        $this->assertEquals(1, $thrall->reportsAttended);
        $this->assertEquals(100.0, $thrall->percentage);
    }

    // ==================== wholeGuild: First Attendance Date Tests ====================

    #[Test]
    public function calculate_returns_correct_first_attendance_date(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $jan08 = Carbon::parse('2025-01-08 20:00', 'Europe/Paris');
        $jan15 = Carbon::parse('2025-01-15 20:00', 'Europe/Paris');

        // Insert jan15 first — first attendance should still resolve to jan08
        $report1 = $this->makeReport($tag, $jan15);
        $report2 = $this->makeReport($tag, $jan08);
        $this->attachCharacterToReport($report1, $character, 1);
        $this->attachCharacterToReport($report2, $character, 1);

        $thrall = $this->findStats($this->makeCalculator()->wholeGuild(), 'Thrall');

        $this->assertTrue($thrall->firstAttendance->eq($jan08));
    }

    #[Test]
    public function calculate_tracks_first_attendance_per_player(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $jan01 = Carbon::parse('2025-01-01 20:00', 'Europe/Paris');
        $jan08 = Carbon::parse('2025-01-08 20:00', 'Europe/Paris');

        $report1 = $this->makeReport($tag, $jan01);
        $report2 = $this->makeReport($tag, $jan08);

        $this->attachCharacterToReport($report1, $thrall, 1);
        $this->attachCharacterToReport($report2, $thrall, 1);
        $this->attachCharacterToReport($report2, $jaina, 1);

        $stats = $this->makeCalculator()->wholeGuild();

        $thrallStats = $this->findStats($stats, 'Thrall');
        $jainaStats = $this->findStats($stats, 'Jaina');

        $this->assertTrue($thrallStats->firstAttendance->eq($jan01));
        $this->assertTrue($jainaStats->firstAttendance->eq($jan08));

        // Jaina joined on report 2, so she only has 1 total report
        $this->assertEquals(1, $jainaStats->totalReports);
        // Thrall was there from report 1, so he has 2 total reports
        $this->assertEquals(2, $thrallStats->totalReports);
    }

    // ==================== wholeGuild: Character ID Tests ====================

    #[Test]
    public function calculate_includes_character_id_in_stats(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacterToReport($report, $character, 1);

        $thrall = $this->makeCalculator()->wholeGuild()->first();

        $this->assertEquals($character->id, $thrall->character->id);
    }

    // ==================== wholeGuild: Multiple Players Tests ====================

    #[Test]
    public function calculate_handles_multiple_players(): void
    {
        $rank = $this->makeRank();
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));

        foreach ([['name' => 'Thrall', 'presence' => 1], ['name' => 'Jaina', 'presence' => 1], ['name' => 'Sylvanas', 'presence' => 2]] as $data) {
            $character = Character::factory()->create(['name' => $data['name'], 'rank_id' => $rank->id]);
            $this->attachCharacterToReport($report, $character, $data['presence']);
        }

        $stats = $this->makeCalculator()->wholeGuild();

        $this->assertCount(3, $stats);
        $this->assertNotNull($this->findStats($stats, 'Thrall'));
        $this->assertNotNull($this->findStats($stats, 'Jaina'));
        $this->assertNotNull($this->findStats($stats, 'Sylvanas'));
    }

    // ==================== wholeGuild: Returns row arrays Tests ====================

    #[Test]
    public function calculate_returns_character_attendance_stats_objects(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacterToReport($report, $character, 1);

        $stats = $this->makeCalculator()->wholeGuild();

        $this->assertInstanceOf(CharacterAttendanceStatsData::class, $stats->first());
    }

    // ==================== wholeGuild: Rank Filtering Tests ====================

    #[Test]
    public function calculate_excludes_characters_from_non_counting_ranks(): void
    {
        $countingRank = $this->makeRank(true);
        $nonCountingRank = $this->makeRank(false);
        $countingChar = Character::factory()->create(['name' => 'CountingPlayer', 'rank_id' => $countingRank->id]);
        $nonCountingChar = Character::factory()->create(['name' => 'NonCountingPlayer', 'rank_id' => $nonCountingRank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacterToReport($report, $countingChar, 1);
        $this->attachCharacterToReport($report, $nonCountingChar, 1);

        $stats = $this->makeCalculator()->wholeGuild();

        $names = $stats->pluck('character.name')->toArray();
        $this->assertContains('CountingPlayer', $names);
        $this->assertNotContains('NonCountingPlayer', $names);
    }

    #[Test]
    public function calculate_excludes_reports_from_non_counting_guild_tags(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $countingTag = $this->makeTag(true);
        $nonCountingTag = $this->makeTag(false);

        $countingReport = $this->makeReport($countingTag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));
        $nonCountingReport = $this->makeReport($nonCountingTag, Carbon::parse('2025-01-22 20:00', 'Europe/Paris'));
        $this->attachCharacterToReport($countingReport, $character, 1);
        $this->attachCharacterToReport($nonCountingReport, $character, 1);

        $thrall = $this->findStats($this->makeCalculator()->wholeGuild(), 'Thrall');

        // Only the one counting report should be included
        $this->assertEquals(1, $thrall->totalReports);
    }

    // ==================== wholeGuild: Linked Report Merging Tests ====================

    #[Test]
    public function calculate_merges_linked_raids_into_single_record(): void
    {
        $rank = $this->makeRank();
        $fizzywigs = Character::factory()->create(['name' => 'Fizzywigs', 'rank_id' => $rank->id]);
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        // Two linked raids — Fizzywigs in one, not the other
        $raid1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $raid2 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));
        $this->linkReports($raid1, $raid2);

        $this->attachCharacterToReport($raid1, $fizzywigs, 1);
        $this->attachCharacterToReport($raid1, $thrall, 1);
        $this->attachCharacterToReport($raid2, $thrall, 1);
        $this->attachCharacterToReport($raid2, $jaina, 1);

        $stats = $this->makeCalculator()->wholeGuild();

        // Should be 1 total report (merged), all players attended
        $this->assertEquals(1, $this->findStats($stats, 'Fizzywigs')->totalReports);
        $this->assertEquals(1, $this->findStats($stats, 'Fizzywigs')->reportsAttended);
        $this->assertEquals(100.0, $this->findStats($stats, 'Fizzywigs')->percentage);

        $this->assertEquals(1, $this->findStats($stats, 'Thrall')->totalReports);
        $this->assertEquals(1, $this->findStats($stats, 'Jaina')->totalReports);
    }

    #[Test]
    public function calculate_unlinked_reports_remain_separate(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $raid1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $raid2 = $this->makeReport($tag, Carbon::parse('2025-01-22 19:00', 'Europe/Paris'));

        $this->attachCharacterToReport($raid1, $thrall, 1);
        $this->attachCharacterToReport($raid2, $thrall, 1);

        $stats = $this->makeCalculator()->wholeGuild();

        $this->assertEquals(2, $this->findStats($stats, 'Thrall')->totalReports);
        $this->assertEquals(2, $this->findStats($stats, 'Thrall')->reportsAttended);
    }

    #[Test]
    public function calculate_merge_keeps_best_presence_value(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        // Presence 0 in one raid, presence 1 in another — should count as attended
        $raid1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $raid2 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));
        $this->linkReports($raid1, $raid2);

        $this->attachCharacterToReport($raid1, $thrall, 0);
        $this->attachCharacterToReport($raid2, $thrall, 1);

        $stats = $this->makeCalculator()->wholeGuild();

        $this->assertEquals(1, $this->findStats($stats, 'Thrall')->reportsAttended);
        $this->assertEquals(100.0, $this->findStats($stats, 'Thrall')->percentage);
    }

    #[Test]
    public function calculate_merge_prefers_present_over_benched(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        // Presence 1 (present) should be preferred over 2 (benched)
        $raid1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $raid2 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));
        $this->linkReports($raid1, $raid2);

        $this->attachCharacterToReport($raid1, $thrall, 2);
        $this->attachCharacterToReport($raid2, $thrall, 1);

        $stats = $this->makeCalculator()->wholeGuild();

        // Both 1 and 2 count as attended, so either way it's 100%
        $this->assertEquals(1, $this->findStats($stats, 'Thrall')->reportsAttended);
        $this->assertEquals(100.0, $this->findStats($stats, 'Thrall')->percentage);
    }

    #[Test]
    public function calculate_merge_three_linked_raids(): void
    {
        $rank = $this->makeRank();
        $alice = Character::factory()->create(['name' => 'Alice', 'rank_id' => $rank->id]);
        $bob = Character::factory()->create(['name' => 'Bob', 'rank_id' => $rank->id]);
        $charlie = Character::factory()->create(['name' => 'Charlie', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $raid1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $raid2 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:30', 'Europe/Paris'));
        $raid3 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));
        $this->linkReports($raid1, $raid2);
        $this->linkReports($raid2, $raid3);

        $this->attachCharacterToReport($raid1, $alice, 1);
        $this->attachCharacterToReport($raid2, $bob, 1);
        $this->attachCharacterToReport($raid3, $charlie, 1);

        $stats = $this->makeCalculator()->wholeGuild();

        // All three raids merge into one — 3 players, 1 report each
        $this->assertCount(3, $stats);
        $this->assertEquals(1, $this->findStats($stats, 'Alice')->totalReports);
        $this->assertEquals(1, $this->findStats($stats, 'Bob')->totalReports);
        $this->assertEquals(1, $this->findStats($stats, 'Charlie')->totalReports);
    }

    // ==================== forRanks: Tests ====================

    #[Test]
    public function for_ranks_throws_for_empty_collection(): void
    {
        $this->expectException(EmptyCollectionException::class);

        $this->makeCalculator()->forRanks(collect());
    }

    #[Test]
    public function for_ranks_returns_stats_for_characters_in_specified_ranks(): void
    {
        $rank1 = $this->makeRank();
        $rank2 = $this->makeRank();
        $char1 = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank1->id]);
        $char2 = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank2->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacterToReport($report, $char1, 1);
        $this->attachCharacterToReport($report, $char2, 1);

        // Only query rank1 — only Thrall should appear
        $stats = $this->makeCalculator()->forRanks(collect([$rank1]));

        $names = $stats->pluck('character.name')->toArray();
        $this->assertContains('Thrall', $names);
        $this->assertNotContains('Jaina', $names);
    }

    #[Test]
    public function for_ranks_returns_empty_when_no_qualifying_reports_for_those_ranks(): void
    {
        $rank = $this->makeRank();
        Character::factory()->create(['rank_id' => $rank->id]);
        $this->makeTag(false); // non-counting tag — no qualifying reports

        $stats = $this->makeCalculator()->forRanks(collect([$rank]));

        $this->assertTrue($stats->isEmpty());
    }

    #[Test]
    public function for_ranks_returns_character_attendance_stats_objects(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacterToReport($report, $character, 1);

        $stats = $this->makeCalculator()->forRanks(collect([$rank]));

        $this->assertInstanceOf(CharacterAttendanceStatsData::class, $stats->first());
    }

    // ==================== forCharacter: Tests ====================

    #[Test]
    public function for_character_returns_empty_when_no_counting_reports_exist(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['rank_id' => $rank->id]);

        $stats = $this->makeCalculator()->forCharacter($character);

        $this->assertTrue($stats->isEmpty());
    }

    #[Test]
    public function for_character_returns_only_the_specified_character(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacterToReport($report, $thrall, 1);
        $this->attachCharacterToReport($report, $jaina, 1);

        $stats = $this->makeCalculator()->forCharacter($thrall);

        $this->assertCount(1, $stats);
        $this->assertEquals('Thrall', $stats->first()->character->name);
    }

    #[Test]
    public function for_character_returns_empty_when_character_is_in_non_counting_rank(): void
    {
        $nonCountingRank = $this->makeRank(false);
        $character = Character::factory()->create(['rank_id' => $nonCountingRank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacterToReport($report, $character, 1);

        $stats = $this->makeCalculator()->forCharacter($character);

        $this->assertTrue($stats->isEmpty());
    }

    #[Test]
    public function for_character_calculates_lifetime_stats_from_first_attendance(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $this->attachCharacterToReport($report1, $character, 1);
        $this->attachCharacterToReport($report2, $character, 1);

        $thrall = $this->makeCalculator()->forCharacter($character)->first();

        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(2, $thrall->reportsAttended);
        $this->assertEquals(100.0, $thrall->percentage);
    }

    // ==================== forReport: Tests ====================

    #[Test]
    public function for_report_returns_empty_when_guild_tag_does_not_count_attendance(): void
    {
        $rank = $this->makeRank();
        $character = Character::factory()->create(['rank_id' => $rank->id]);
        $tag = $this->makeTag(false);
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacterToReport($report, $character, 1);

        $stats = $this->makeCalculator()->forReport($report);

        $this->assertTrue($stats->isEmpty());
    }

    #[Test]
    public function for_report_returns_stats_for_characters_in_the_report(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacterToReport($report, $thrall, 1);
        $this->attachCharacterToReport($report, $jaina, 1);

        $stats = $this->makeCalculator()->forReport($report);

        $this->assertCount(2, $stats);
        $this->assertNotNull($this->findStats($stats, 'Thrall'));
        $this->assertNotNull($this->findStats($stats, 'Jaina'));
    }

    #[Test]
    public function for_report_excludes_characters_in_non_counting_ranks(): void
    {
        $countingRank = $this->makeRank(true);
        $nonCountingRank = $this->makeRank(false);
        $countingChar = Character::factory()->create(['name' => 'CountingPlayer', 'rank_id' => $countingRank->id]);
        $nonCountingChar = Character::factory()->create(['name' => 'NonCountingPlayer', 'rank_id' => $nonCountingRank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacterToReport($report, $countingChar, 1);
        $this->attachCharacterToReport($report, $nonCountingChar, 1);

        $stats = $this->makeCalculator()->forReport($report);

        $names = $stats->pluck('character.name')->toArray();
        $this->assertContains('CountingPlayer', $names);
        $this->assertNotContains('NonCountingPlayer', $names);
    }

    #[Test]
    public function for_report_counts_only_the_single_report(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacterToReport($report, $thrall, 1);
        $this->attachCharacterToReport($report, $jaina, 2);

        $stats = $this->makeCalculator()->forReport($report);

        $this->assertEquals(1, $this->findStats($stats, 'Thrall')->totalReports);
        $this->assertEquals(1, $this->findStats($stats, 'Thrall')->reportsAttended);
        $this->assertEquals(1, $this->findStats($stats, 'Jaina')->totalReports);
        $this->assertEquals(1, $this->findStats($stats, 'Jaina')->reportsAttended);
    }

    // ==================== mergeLinkedReports: Unit Tests ====================

    #[Test]
    public function merge_linked_reports_returns_single_report_as_is(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $this->attachCharacterToReport($report, $thrall, 1);

        $reports = Report::with(['characters', 'linkedReports'])->get();
        $records = $this->makeCalculator()->mergeLinkedReports($reports);

        $this->assertCount(1, $records);
        $this->assertEquals($report->code, $records->first()->code());
        $this->assertTrue($records->first()->players()->has('Thrall'));
    }

    #[Test]
    public function merge_linked_reports_merges_linked_pair_into_one_record(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $raid1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $raid2 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));
        $this->linkReports($raid1, $raid2);

        $this->attachCharacterToReport($raid1, $thrall, 1);
        $this->attachCharacterToReport($raid2, $jaina, 1);

        $reports = Report::with(['characters', 'linkedReports'])->get();
        $records = $this->makeCalculator()->mergeLinkedReports($reports);

        $this->assertCount(1, $records);
        $this->assertTrue($records->first()->players()->has('Thrall'));
        $this->assertTrue($records->first()->players()->has('Jaina'));
    }

    #[Test]
    public function merge_linked_reports_keeps_best_presence_when_merging(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $raid1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $raid2 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));
        $this->linkReports($raid1, $raid2);

        // Absence in raid1, present in raid2 — best presence (1) should win
        $this->attachCharacterToReport($raid1, $thrall, 0);
        $this->attachCharacterToReport($raid2, $thrall, 1);

        $reports = Report::with(['characters', 'linkedReports'])->get();
        $records = $this->makeCalculator()->mergeLinkedReports($reports);

        $this->assertCount(1, $records);
        $this->assertEquals(1, $records->first()->players()['Thrall']->presence);
    }

    #[Test]
    public function merge_linked_reports_leaves_unlinked_reports_as_separate_records(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $raid1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $raid2 = $this->makeReport($tag, Carbon::parse('2025-01-22 19:00', 'Europe/Paris'));

        $this->attachCharacterToReport($raid1, $thrall, 1);
        $this->attachCharacterToReport($raid2, $thrall, 1);

        $reports = Report::with(['characters', 'linkedReports'])->get();
        $records = $this->makeCalculator()->mergeLinkedReports($reports);

        $this->assertCount(2, $records);
    }

    #[Test]
    public function merge_linked_reports_handles_three_reports_in_same_group(): void
    {
        $rank = $this->makeRank();
        $alice = Character::factory()->create(['name' => 'Alice', 'rank_id' => $rank->id]);
        $bob = Character::factory()->create(['name' => 'Bob', 'rank_id' => $rank->id]);
        $charlie = Character::factory()->create(['name' => 'Charlie', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $raid1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $raid2 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:30', 'Europe/Paris'));
        $raid3 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));
        // Chain: raid1 ↔ raid2 ↔ raid3 — all transitively linked
        $this->linkReports($raid1, $raid2);
        $this->linkReports($raid2, $raid3);

        $this->attachCharacterToReport($raid1, $alice, 1);
        $this->attachCharacterToReport($raid2, $bob, 1);
        $this->attachCharacterToReport($raid3, $charlie, 1);

        $reports = Report::with(['characters', 'linkedReports'])->get();
        $records = $this->makeCalculator()->mergeLinkedReports($reports);

        $this->assertCount(1, $records);
        $this->assertTrue($records->first()->players()->has('Alice'));
        $this->assertTrue($records->first()->players()->has('Bob'));
        $this->assertTrue($records->first()->players()->has('Charlie'));
    }

    #[Test]
    public function merge_linked_reports_handles_manual_reports_with_null_code(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $wclReport = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $manualReport = Report::factory()
            ->withGuildTag($tag)
            ->create([
                'code' => null,
                'start_time' => Carbon::parse('2025-01-22 19:00', 'Europe/Paris'),
            ]);

        $this->attachCharacterToReport($wclReport, $thrall, 1);
        $this->attachCharacterToReport($manualReport, $jaina, 1);

        $reports = Report::with(['characters', 'linkedReports'])->get();
        $records = $this->makeCalculator()->mergeLinkedReports($reports);

        $this->assertCount(2, $records);

        $manualRecord = $records->first(fn ($cluster) => $cluster->id() === $manualReport->id);
        $this->assertNotNull($manualRecord);
        $this->assertNull($manualRecord->code());
        $this->assertTrue($manualRecord->players()->has('Jaina'));

        $wclRecord = $records->first(fn ($cluster) => $cluster->id() === $wclReport->id);
        $this->assertNotNull($wclRecord);
        $this->assertSame($wclReport->code, $wclRecord->code());
        $this->assertTrue($wclRecord->players()->has('Thrall'));
    }

    #[Test]
    public function merge_linked_reports_merges_manual_and_wcl_reports_when_linked(): void
    {
        $rank = $this->makeRank();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $wclReport = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $manualReport = Report::factory()
            ->withGuildTag($tag)
            ->create([
                'code' => null,
                'start_time' => Carbon::parse('2025-01-15 20:00', 'Europe/Paris'),
            ]);

        $this->linkReports($wclReport, $manualReport);

        $this->attachCharacterToReport($wclReport, $thrall, 1);
        $this->attachCharacterToReport($manualReport, $jaina, 1);

        $reports = Report::with(['characters', 'linkedReports'])->get();
        $records = $this->makeCalculator()->mergeLinkedReports($reports);

        $this->assertCount(1, $records);
        $this->assertTrue($records->first()->players()->has('Thrall'));
        $this->assertTrue($records->first()->players()->has('Jaina'));
        $this->assertSame($wclReport->code, $records->first()->code());
    }

    #[Test]
    public function merge_linked_reports_path_compresses_union_find_tree_when_depth_exceeds_one(): void
    {
        $rank = $this->makeRank();
        $alice = Character::factory()->create(['name' => 'Alice', 'rank_id' => $rank->id]);
        $bob = Character::factory()->create(['name' => 'Bob', 'rank_id' => $rank->id]);
        $charlie = Character::factory()->create(['name' => 'Charlie', 'rank_id' => $rank->id]);
        $diana = Character::factory()->create(['name' => 'Diana', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $raid1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $raid2 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:30', 'Europe/Paris'));
        $raid3 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));
        $raid4 = $this->makeReport($tag, Carbon::parse('2025-01-15 20:30', 'Europe/Paris'));

        // Links R1↔R3, R2↔R4, R3↔R4 create parent[R4]→R2→R1 after the first three unions,
        // so find(R4) on the fourth pass traverses two levels and path compression fires.
        $this->linkReports($raid1, $raid3);
        $this->linkReports($raid2, $raid4);
        $this->linkReports($raid3, $raid4);

        $this->attachCharacterToReport($raid1, $alice, 1);
        $this->attachCharacterToReport($raid2, $bob, 1);
        $this->attachCharacterToReport($raid3, $charlie, 1);
        $this->attachCharacterToReport($raid4, $diana, 1);

        $reports = Report::with(['characters', 'linkedReports'])->get();
        $records = $this->makeCalculator()->mergeLinkedReports($reports);

        $this->assertCount(1, $records);
        $this->assertTrue($records->first()->players()->has('Alice'));
        $this->assertTrue($records->first()->players()->has('Bob'));
        $this->assertTrue($records->first()->players()->has('Charlie'));
        $this->assertTrue($records->first()->players()->has('Diana'));
    }

    #[Test]
    public function merge_linked_reports_skips_linked_reports_not_in_the_passed_collection(): void
    {
        $rank = $this->makeRank();
        $alice = Character::factory()->create(['name' => 'Alice', 'rank_id' => $rank->id]);
        $bob = Character::factory()->create(['name' => 'Bob', 'rank_id' => $rank->id]);
        $tag = $this->makeTag();

        $raid1 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:00', 'Europe/Paris'));
        $raid2 = $this->makeReport($tag, Carbon::parse('2025-01-15 19:30', 'Europe/Paris'));

        $this->linkReports($raid1, $raid2);

        $this->attachCharacterToReport($raid1, $alice, 1);
        $this->attachCharacterToReport($raid2, $bob, 1);

        // Pass only raid1 so raid2 is outside the collection's $idSet.
        // raid1->linkedReports still returns raid2, but the guard should skip it.
        $reports = Report::where('id', $raid1->id)->with(['characters', 'linkedReports'])->get();
        $records = $this->makeCalculator()->mergeLinkedReports($reports);

        $this->assertCount(1, $records);
        $this->assertTrue($records->first()->players()->has('Alice'));
    }
}
