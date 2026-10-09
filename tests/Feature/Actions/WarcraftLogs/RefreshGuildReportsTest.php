<?php

namespace Tests\Feature\Actions\WarcraftLogs;

use App\Actions\WarcraftLogs\RefreshGuildReports;
use App\Models\GameVersion;
use App\Models\Report;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
#[Group('warcraftlogs-integration')]
class RefreshGuildReportsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_rederives_every_report_of_the_guild(): void
    {
        $guild = Guild::factory()->create();
        $gameVersion = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2024-11-22 00:00:00']);
        $reports = Report::factory()->forGuild($guild)->count(2)->create(['start_time' => '2025-03-14 19:30:00']);
        Report::whereKey($reports->modelKeys())->update(['game_version_id' => null]);

        RefreshGuildReports::run($guild);

        foreach ($reports as $report) {
            $this->assertSame($gameVersion->id, $report->fresh()->game_version_id);
        }
    }

    #[Test]
    public function it_leaves_another_guilds_reports_alone(): void
    {
        $guild = Guild::factory()->create();
        $otherGuild = Guild::factory()->create();
        GameVersion::factory()->forGuild($otherGuild)->create(['release_date' => '2024-11-22 00:00:00']);
        $otherReport = Report::factory()->forGuild($otherGuild)->create(['start_time' => '2025-03-14 19:30:00']);
        Report::whereKey($otherReport->id)->update(['game_version_id' => null]);

        RefreshGuildReports::run($guild);

        $this->assertNull($otherReport->fresh()->game_version_id);
    }
}
