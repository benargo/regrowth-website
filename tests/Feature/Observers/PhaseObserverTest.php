<?php

namespace Tests\Feature\Observers;

use App\Models\Event;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\Report;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class PhaseObserverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creating_a_phase_rederives_its_versions_reports(): void
    {
        [$gameVersion, $report] = $this->versionWithReport('2026-02-12 19:30:00');

        $phase = Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => '2026-01-01 00:00:00']);

        $this->assertSame($phase->id, $report->fresh()->phase_id);
    }

    #[Test]
    public function changing_a_phases_start_date_rederives_the_phase(): void
    {
        [$gameVersion, $report] = $this->versionWithReport('2026-02-12 19:30:00');
        $phaseOne = Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => '2026-01-01 00:00:00']);
        $phaseTwo = Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => '2026-06-01 00:00:00']);
        $this->assertSame($phaseOne->id, $report->fresh()->phase_id);

        $phaseTwo->update(['start_date' => '2026-02-01 00:00:00']);

        $this->assertSame($phaseTwo->id, $report->fresh()->phase_id);
    }

    #[Test]
    public function deleting_a_phase_moves_its_reports_to_the_previous_phase(): void
    {
        [$gameVersion, $report] = $this->versionWithReport('2026-02-12 19:30:00');
        $previous = Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => '2026-01-01 00:00:00']);
        $latest = Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => '2026-02-01 00:00:00']);
        $this->assertSame($latest->id, $report->fresh()->phase_id);

        $latest->delete();

        $this->assertSame($previous->id, $report->fresh()->phase_id);
    }

    #[Test]
    public function moving_a_phase_to_another_version_rederives_both_versions_reports(): void
    {
        [$oldVersion, $oldReport] = $this->versionWithReport('2026-02-12 19:30:00');
        [$newVersion, $newReport] = $this->versionWithReport('2026-02-12 19:30:00');
        $phase = Phase::factory()->forGameVersion($oldVersion)->create(['start_date' => '2026-01-01 00:00:00']);
        $this->assertSame($phase->id, $oldReport->fresh()->phase_id);

        $phase->update(['game_version_id' => $newVersion->id]);

        $this->assertNull($oldReport->fresh()->phase_id);
        $this->assertSame($phase->id, $newReport->fresh()->phase_id);
    }

    #[Test]
    public function moving_a_phase_to_another_game_version_re_resolves_its_events(): void
    {
        $phase = Phase::factory()->forGameVersion(GameVersion::factory()->create())->create();
        $event = Event::factory()->withRaids([Raid::factory()->for($phase)->create()])->create();
        $event->refreshGameVersion();
        $newVersion = GameVersion::factory()->create();

        $phase->update(['game_version_id' => $newVersion->id]);

        $this->assertSame($newVersion->id, $event->fresh()->game_version_id);
    }

    #[Test]
    public function unlinking_a_phase_from_its_game_version_clears_its_reports_phase_and_its_events_version(): void
    {
        [$gameVersion, $report] = $this->versionWithReport('2026-02-12 19:30:00');
        $phase = Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => '2026-01-01 00:00:00']);
        $event = Event::factory()->withRaids([Raid::factory()->for($phase)->create()])->create();
        $event->refreshGameVersion();

        $phase->update(['game_version_id' => null]);

        $this->assertNull($report->fresh()->phase_id);
        $this->assertSame($gameVersion->id, $report->fresh()->game_version_id);
        $this->assertNull($event->fresh()->game_version_id);
    }

    #[Test]
    public function it_leaves_events_of_other_phases_untouched(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->forGameVersion($gameVersion)->create();
        $otherEvent = Event::factory()->forGameVersion($gameVersion)->create();

        $phase->update(['game_version_id' => GameVersion::factory()->create()->id]);

        $this->assertSame($gameVersion->id, $otherEvent->fresh()->game_version_id);
    }

    // ==================== helpers ====================

    /**
     * A game version released before the report, in its own guild, and a
     * report in that guild.
     *
     * @return array{0: GameVersion, 1: Report}
     */
    private function versionWithReport(string $startTime): array
    {
        $guild = Guild::factory()->create();
        $gameVersion = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2024-11-22 00:00:00']);
        $report = Report::factory()->forGuild($guild)->create(['start_time' => $startTime]);

        return [$gameVersion, $report];
    }
}
