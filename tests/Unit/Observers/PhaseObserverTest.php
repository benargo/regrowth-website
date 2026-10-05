<?php

namespace Tests\Unit\Observers;

use App\Models\Event;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\Report;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class PhaseObserverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function moving_a_phase_to_another_game_version_re_resolves_its_reports(): void
    {
        $phase = Phase::factory()->forGameVersion(GameVersion::factory()->create())->create();
        $report = Report::factory()->withGuildTag(GuildTag::factory()->withPhase($phase)->create())->create();
        $newVersion = GameVersion::factory()->create();

        $phase->update(['game_version_id' => $newVersion->id]);

        $this->assertSame($newVersion->id, $report->fresh()->game_version_id);
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
    public function unlinking_a_phase_from_its_game_version_clears_its_reports_and_events(): void
    {
        $phase = Phase::factory()->forGameVersion(GameVersion::factory()->create())->create();
        $report = Report::factory()->withGuildTag(GuildTag::factory()->withPhase($phase)->create())->create();
        $event = Event::factory()->withRaids([Raid::factory()->for($phase)->create()])->create();
        $event->refreshGameVersion();

        $phase->update(['game_version_id' => null]);

        $this->assertNull($report->fresh()->game_version_id);
        $this->assertNull($event->fresh()->game_version_id);
    }

    #[Test]
    public function it_leaves_reports_and_events_of_other_phases_untouched(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->forGameVersion($gameVersion)->create();
        $otherReport = Report::factory()->forGameVersion($gameVersion)->create();
        $otherEvent = Event::factory()->forGameVersion($gameVersion)->create();

        $phase->update(['game_version_id' => GameVersion::factory()->create()->id]);

        $this->assertSame($gameVersion->id, $otherReport->fresh()->game_version_id);
        $this->assertSame($gameVersion->id, $otherEvent->fresh()->game_version_id);
    }
}
