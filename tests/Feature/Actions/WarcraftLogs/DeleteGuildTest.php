<?php

namespace Tests\Feature\Actions\WarcraftLogs;

use App\Actions\WarcraftLogs\DeleteGuild;
use App\Jobs\BuildAddonExportFile;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Report;
use App\Models\WarcraftLogs\Guild;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
#[Group('warcraftlogs-integration')]
class DeleteGuildTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([BuildAddonExportFile::class]);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_deletes_the_guild(): void
    {
        $guild = Guild::factory()->create();

        DeleteGuild::run($guild);

        $this->assertModelMissing($guild);
    }

    #[Test]
    public function it_detaches_the_guilds_game_versions_and_reports(): void
    {
        [$guild, $gameVersion, , $report] = $this->guildWithData();

        DeleteGuild::run($guild);

        $this->assertNull($gameVersion->fresh()->warcraft_logs_guild_id);
        $this->assertNull($report->fresh()->warcraft_logs_guild_id);
    }

    #[Test]
    public function it_deletes_the_guilds_tags(): void
    {
        [$guild, , $guildTag] = $this->guildWithData();

        DeleteGuild::run($guild);

        $this->assertModelMissing($guildTag);
    }

    #[Test]
    public function it_keeps_every_report_and_attendance_row(): void
    {
        [$guild, $gameVersion, , $report] = $this->guildWithData();
        $linkedReport = Report::factory()->forGuild($guild)->create();
        $report->linkedReports()->attach($linkedReport->id);
        $character = Character::factory()->create();
        $report->characters()->attach($character->id, ['presence' => 1]);

        DeleteGuild::run($guild);

        $this->assertModelExists($gameVersion);
        $this->assertModelExists($report);
        $this->assertModelExists($linkedReport);
        $this->assertNull($report->fresh()->guild_tag_id);
        $this->assertDatabaseHas('pivot_characters_raid_reports', ['character_id' => $character->id, 'raid_report_id' => $report->id, 'presence' => 1]);
        $this->assertDatabaseHas('pivot_report_links', ['report_1' => $report->id, 'report_2' => $linkedReport->id]);
    }

    #[Test]
    public function it_rederives_the_detached_reports_to_no_version_or_phase(): void
    {
        [$guild, $gameVersion, , $report] = $this->guildWithData();
        $phase = Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => '2025-01-01 00:00:00']);
        $report->save();
        $this->assertSame($phase->id, $report->fresh()->phase_id);

        DeleteGuild::run($guild);

        $fresh = $report->fresh();
        $this->assertNull($fresh->game_version_id);
        $this->assertNull($fresh->phase_id);
    }

    #[Test]
    public function it_leaves_another_guilds_rows_alone(): void
    {
        [$guild] = $this->guildWithData();
        [$otherGuild, $otherVersion, $otherTag, $otherReport] = $this->guildWithData();

        DeleteGuild::run($guild);

        $this->assertSame($otherGuild->id, $otherVersion->fresh()->warcraft_logs_guild_id);
        $this->assertModelExists($otherTag);
        $this->assertSame($otherGuild->id, $otherTag->fresh()->warcraft_logs_guild_id);
        $this->assertSame($otherVersion->id, $otherReport->fresh()->game_version_id);
    }

    // ==================== helpers ====================

    /**
     * A guild with a version released before its report, a counting tag, and
     * a report in that tag.
     *
     * @return array{0: Guild, 1: GameVersion, 2: GuildTag, 3: Report}
     */
    private function guildWithData(): array
    {
        $guild = Guild::factory()->create();
        $gameVersion = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2024-11-22 00:00:00']);
        $guildTag = GuildTag::factory()->forGuild($guild)->countsAttendance()->create();
        $report = Report::factory()->forGuild($guild)->withGuildTag($guildTag)->create(['start_time' => '2025-03-14 19:30:00']);

        return [$guild, $gameVersion, $guildTag, $report];
    }
}
