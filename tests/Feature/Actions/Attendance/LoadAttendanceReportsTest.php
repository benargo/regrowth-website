<?php

namespace Tests\Feature\Actions\Attendance;

use App\Actions\Attendance\LoadAttendanceReports;
use App\Models\GameVersion;
use App\Models\Report;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Zone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsAttendanceFixtures;
use Tests\TestCase;

#[Group('raiding')]
class LoadAttendanceReportsTest extends TestCase
{
    use BuildsAttendanceFixtures;
    use RefreshDatabase;

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gameVersion = $this->makeAttendanceVersion();
    }

    // ==================== version and tags ====================

    #[Test]
    public function it_loads_only_the_versions_reports(): void
    {
        $own = $this->makeReport($this->gameVersion, '2025-01-01 19:00');
        $otherVersion = $this->makeAttendanceVersion();
        $this->makeReport($otherVersion, '2025-01-01 19:00');
        $unversioned = Report::factory()->withGuildTag(GuildTag::factory()->countsAttendance()->create())->create(['start_time' => '2025-01-01 19:00']);

        $reports = $this->load();

        $this->assertNull($unversioned->game_version_id);
        $this->assertEquals([$own->id], $reports->modelKeys());
    }

    #[Test]
    public function it_excludes_reports_whose_tag_does_not_count_attendance(): void
    {
        $counted = $this->makeReport($this->gameVersion, '2025-01-01 19:00');
        $this->makeReport($this->gameVersion, '2025-01-08 19:00', $this->makeTag($this->gameVersion, countsAttendance: false));

        $this->assertEquals([$counted->id], $this->load()->modelKeys());
    }

    #[Test]
    public function it_includes_a_versioned_report_whose_tag_has_no_guild(): void
    {
        $report = $this->makeReport($this->gameVersion, '2025-01-01 19:00', GuildTag::factory()->countsAttendance()->create());

        $this->assertNull($report->guildTag->warcraft_logs_guild_id);
        $this->assertEquals([$report->id], $this->load()->modelKeys());
    }

    #[Test]
    public function it_limits_reports_to_the_given_guild_tags(): void
    {
        $tag = $this->makeTag($this->gameVersion, countsAttendance: false);
        $tagged = $this->makeReport($this->gameVersion, '2025-01-01 19:00', $tag);
        $this->makeReport($this->gameVersion, '2025-01-08 19:00');

        $this->assertEquals([$tagged->id], $this->load(guildTagIds: [$tag->id])->modelKeys());
    }

    #[Test]
    public function it_loads_no_reports_for_an_empty_guild_tag_list(): void
    {
        $this->makeReport($this->gameVersion, '2025-01-01 19:00');

        $this->assertTrue($this->load(guildTagIds: [])->isEmpty());
    }

    // ==================== zones and dates ====================

    #[Test]
    public function it_limits_reports_to_the_given_zones(): void
    {
        $zone = Zone::factory()->create();
        $inZone = $this->makeReport($this->gameVersion, '2025-01-01 19:00', attributes: ['zone_id' => $zone->id]);
        $this->makeReport($this->gameVersion, '2025-01-08 19:00', attributes: ['zone_id' => Zone::factory()->create()->id]);

        $this->assertEquals([$inZone->id], $this->load(zoneIds: [$zone->id])->modelKeys());
    }

    #[Test]
    public function it_loads_no_reports_for_an_empty_zone_list(): void
    {
        $this->makeReport($this->gameVersion, '2025-01-01 19:00');

        $this->assertTrue($this->load(zoneIds: [])->isEmpty());
    }

    #[Test]
    public function it_includes_only_reports_dated_after_since(): void
    {
        $this->makeReport($this->gameVersion, '2025-01-07 23:30');
        $nextDay = $this->makeReport($this->gameVersion, '2025-01-08 00:30');

        $this->assertEquals([$nextDay->id], $this->load(since: Carbon::parse('2025-01-07'))->modelKeys());
    }

    #[Test]
    public function it_includes_only_reports_dated_before_before(): void
    {
        $previousDay = $this->makeReport($this->gameVersion, '2025-01-07 23:30');
        $this->makeReport($this->gameVersion, '2025-01-08 00:30');

        $this->assertEquals([$previousDay->id], $this->load(before: Carbon::parse('2025-01-08'))->modelKeys());
    }

    // ==================== characters ====================

    #[Test]
    public function it_limits_characters_to_the_resolved_ranks(): void
    {
        $rank = $this->makeRank($this->gameVersion);
        $ranked = $this->makeCharacter($this->gameVersion, $rank);
        $unranked = $this->makeCharacter($this->gameVersion);
        $report = $this->makeReport($this->gameVersion, '2025-01-01 19:00');
        $this->attend($report, $ranked);
        $this->attend($report, $unranked);

        $this->assertEquals([$ranked->id], $this->load(rankIds: [$rank->id])->first()->characters->modelKeys());
    }

    #[Test]
    public function it_loads_every_character_when_asked(): void
    {
        $rank = $this->makeRank($this->gameVersion);
        $ranked = $this->makeCharacter($this->gameVersion, $rank);
        $unranked = $this->makeCharacter($this->gameVersion);
        $report = $this->makeReport($this->gameVersion, '2025-01-01 19:00');
        $this->attend($report, $ranked);
        $this->attend($report, $unranked);

        $characters = $this->load(rankIds: [$rank->id], withAllCharacters: true)->first()->characters;

        $this->assertEqualsCanonicalizing([$ranked->id, $unranked->id], $characters->modelKeys());
    }

    #[Test]
    public function it_eager_loads_linked_reports_zone_and_presence(): void
    {
        $rank = $this->makeRank($this->gameVersion);
        $report = $this->makeReport($this->gameVersion, '2025-01-01 19:00');
        $this->attend($report, $this->makeCharacter($this->gameVersion, $rank));

        $loaded = $this->load(rankIds: [$rank->id])->first();

        $this->assertTrue($loaded->relationLoaded('linkedReports'));
        $this->assertTrue($loaded->relationLoaded('zone'));
        $this->assertSame(1, (int) $loaded->characters->first()->pivot->presence);
    }

    // ==================== helpers ====================

    /**
     * @param  array<int, int>  $rankIds
     * @param  array<int, int>|null  $zoneIds
     * @param  array<int, int>|null  $guildTagIds
     */
    private function load(array $rankIds = [], ?array $zoneIds = null, ?array $guildTagIds = null, ?Carbon $since = null, ?Carbon $before = null, bool $withAllCharacters = false): EloquentCollection
    {
        return LoadAttendanceReports::run($this->gameVersion, $rankIds, $zoneIds, $guildTagIds, $since, $before, $withAllCharacters);
    }
}
