<?php

namespace Tests\Feature\Observers;

use App\Actions\Events\RefreshEventGameVersion;
use App\Jobs\BuildAddonExportFile;
use App\Listeners\FlushRaidingCache;
use App\Models\Event;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\Report;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
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
        RefreshEventGameVersion::run($event);
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
        RefreshEventGameVersion::run($event);

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

    #[Test]
    public function updating_a_phase_schedules_addon_export_build(): void
    {
        $phase = Phase::factory()->create();
        Bus::fake([BuildAddonExportFile::class]);

        $phase->update(['description' => 'Renamed phase']);

        Bus::assertDispatched(BuildAddonExportFile::class, fn (BuildAddonExportFile $job) => $job->delay !== null);
    }

    #[Test]
    public function creating_a_phase_schedules_addon_export_build(): void
    {
        Bus::fake([BuildAddonExportFile::class]);

        Phase::factory()->create();

        Bus::assertDispatched(BuildAddonExportFile::class, fn (BuildAddonExportFile $job) => $job->delay !== null);
    }

    #[Test]
    public function deleting_a_phase_schedules_addon_export_build(): void
    {
        $phase = Phase::factory()->create();
        Bus::fake([BuildAddonExportFile::class]);

        $phase->delete();

        Bus::assertDispatched(BuildAddonExportFile::class, fn (BuildAddonExportFile $job) => $job->delay !== null);
    }

    #[Test]
    public function creating_a_phase_flushes_the_raiding_cache(): void
    {
        $this->mock(FlushRaidingCache::class)->shouldReceive('flush')->once();

        Phase::factory()->create();
    }

    #[Test]
    public function updating_a_phase_flushes_the_raiding_cache(): void
    {
        $phase = Phase::factory()->create();
        $this->mock(FlushRaidingCache::class)->shouldReceive('flush')->once();

        $phase->update(['description' => 'Renamed phase']);
    }

    #[Test]
    public function deleting_a_phase_flushes_the_raiding_cache(): void
    {
        $phase = Phase::factory()->create();
        $this->mock(FlushRaidingCache::class)->shouldReceive('flush')->once();

        $phase->delete();
    }

    // ==================== sort order ====================

    #[Test]
    public function clearing_a_start_date_moves_the_phase_to_the_end_of_its_unscheduled_list(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $first = Phase::factory()->forGameVersion($gameVersion)->unscheduled()->create();
        $second = Phase::factory()->forGameVersion($gameVersion)->unscheduled()->create();
        $dated = Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => '2026-01-01 00:00:00']);

        $dated->update(['start_date' => null]);

        $this->assertSame([$first->id, $second->id, $dated->id], Phase::inReleaseOrder()->pluck('id')->all());
    }

    #[Test]
    public function moving_an_unscheduled_phase_to_another_game_version_puts_it_last_there(): void
    {
        $newVersion = GameVersion::factory()->create();
        $alreadyThere = Phase::factory()->forGameVersion($newVersion)->unscheduled()->create();
        $moving = Phase::factory()->forGameVersion(GameVersion::factory()->create())->unscheduled()->create();

        $moving->update(['game_version_id' => $newVersion->id]);

        $this->assertSame($alreadyThere->sort_order + 1, $moving->fresh()->sort_order);
    }

    #[Test]
    public function editing_an_unscheduled_phases_description_keeps_its_place(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $first = Phase::factory()->forGameVersion($gameVersion)->unscheduled()->create();
        Phase::factory()->forGameVersion($gameVersion)->unscheduled()->create();

        $first->update(['description' => 'Renamed']);

        $this->assertSame(1, $first->fresh()->sort_order);
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
