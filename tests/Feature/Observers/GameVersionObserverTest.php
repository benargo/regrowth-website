<?php

namespace Tests\Feature\Observers;

use App\Models\GameVersion;
use App\Models\Report;
use App\Models\WarcraftLogs\Guild;
use App\Observers\GameVersionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[Group('raiding')]
#[Group('warcraftlogs-integration')]
class GameVersionObserverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[Group('contract')]
    public function it_observes_the_game_version_model(): void
    {
        $attributes = (new ReflectionClass(GameVersion::class))->getAttributes(ObservedBy::class);

        $this->assertContains(GameVersionObserver::class, $attributes[0]->getArguments()[0]);
    }

    #[Test]
    public function creating_a_version_rederives_its_guilds_reports(): void
    {
        $guild = Guild::factory()->create();
        $report = Report::factory()->forGuild($guild)->create(['start_time' => '2025-03-14 19:30:00']);

        $gameVersion = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2024-11-22 00:00:00']);

        $this->assertSame($gameVersion->id, $report->fresh()->game_version_id);
    }

    #[Test]
    public function changing_a_versions_release_date_moves_a_report_between_versions(): void
    {
        $guild = Guild::factory()->create();
        $classic = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2021-09-04 00:00:00']);
        $anniversary = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2026-02-06 00:00:00']);
        $report = Report::factory()->forGuild($guild)->create(['start_time' => '2025-03-14 19:30:00']);
        $this->assertSame($classic->id, $report->game_version_id);

        $anniversary->update(['release_date' => '2024-11-22 00:00:00']);

        $this->assertSame($anniversary->id, $report->fresh()->game_version_id);
    }

    #[Test]
    public function moving_a_version_to_another_guild_rederives_both_guilds_reports(): void
    {
        $oldGuild = Guild::factory()->create();
        $newGuild = Guild::factory()->create();
        $gameVersion = GameVersion::factory()->forGuild($oldGuild)->create(['release_date' => '2024-11-22 00:00:00']);
        $oldGuildsReport = Report::factory()->forGuild($oldGuild)->create(['start_time' => '2025-03-14 19:30:00']);
        $newGuildsReport = Report::factory()->forGuild($newGuild)->create(['start_time' => '2025-03-14 19:30:00']);

        $gameVersion->update(['warcraft_logs_guild_id' => $newGuild->id]);

        $this->assertNull($oldGuildsReport->fresh()->game_version_id);
        $this->assertSame($gameVersion->id, $newGuildsReport->fresh()->game_version_id);
    }

    #[Test]
    public function deleting_a_version_moves_its_reports_to_the_previous_version(): void
    {
        $guild = Guild::factory()->create();
        $previous = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2021-09-04 00:00:00']);
        $latest = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2024-11-22 00:00:00']);
        $report = Report::factory()->forGuild($guild)->create(['start_time' => '2025-03-14 19:30:00']);
        $this->assertSame($latest->id, $report->game_version_id);

        $latest->delete();

        $this->assertSame($previous->id, $report->fresh()->game_version_id);
    }

    #[Test]
    public function an_unrelated_change_leaves_the_guilds_reports_alone(): void
    {
        $guild = Guild::factory()->create();
        $gameVersion = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2024-11-22 00:00:00']);
        $report = Report::factory()->forGuild($guild)->create(['start_time' => '2025-03-14 19:30:00']);
        $updatedAt = $report->fresh()->updated_at;
        $this->travel(1)->minutes();

        $gameVersion->update(['title' => 'Renamed']);

        $this->assertTrue($report->fresh()->updated_at->equalTo($updatedAt));
    }

    #[Test]
    public function it_leaves_another_guilds_reports_alone(): void
    {
        $otherGuild = Guild::factory()->create();
        $otherVersion = GameVersion::factory()->forGuild($otherGuild)->create(['release_date' => '2024-11-22 00:00:00']);
        $otherReport = Report::factory()->forGuild($otherGuild)->create(['start_time' => '2025-03-14 19:30:00']);
        $updatedAt = $otherReport->fresh()->updated_at;
        $this->travel(1)->minutes();

        GameVersion::factory()->forGuild()->create(['release_date' => '2025-01-01 00:00:00']);

        $this->assertSame($otherVersion->id, $otherReport->fresh()->game_version_id);
        $this->assertTrue($otherReport->fresh()->updated_at->equalTo($updatedAt));
    }
}
