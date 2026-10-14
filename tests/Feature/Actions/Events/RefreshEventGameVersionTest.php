<?php

namespace Tests\Feature\Actions\Events;

use App\Actions\Events\RefreshEventGameVersion;
use App\Models\Event;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class RefreshEventGameVersionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    public function it_stores_the_game_version_the_events_raids_share(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $event = Event::factory()->withRaids([
            Raid::factory()->for(Phase::factory()->forGameVersion($gameVersion))->create(),
            Raid::factory()->for(Phase::factory()->forGameVersion($gameVersion))->create(),
        ])->create();

        RefreshEventGameVersion::run($event);

        $this->assertSame($gameVersion->id, $event->fresh()->game_version_id);
    }

    #[Test]
    public function it_stores_null_when_the_event_has_no_raids(): void
    {
        $event = Event::factory()->forGameVersion(GameVersion::factory()->create())->create();
        $event->raids()->detach();

        RefreshEventGameVersion::run($event);

        $this->assertNull($event->fresh()->game_version_id);
    }

    #[Test]
    public function it_stores_null_when_the_events_raids_span_game_versions(): void
    {
        $event = Event::factory()->withRaids([
            Raid::factory()->for(Phase::factory()->forGameVersion(GameVersion::factory()->create()))->create(),
            Raid::factory()->for(Phase::factory()->forGameVersion(GameVersion::factory()->create()))->create(),
        ])->create();

        RefreshEventGameVersion::run($event);

        $this->assertNull($event->fresh()->game_version_id);
    }

    #[Test]
    public function it_stores_null_when_a_raids_phase_has_no_game_version(): void
    {
        $event = Event::factory()->withRaids([
            Raid::factory()->for(Phase::factory()->forGameVersion(GameVersion::factory()->create()))->create(),
            Raid::factory()->for(Phase::factory())->create(),
        ])->create();

        RefreshEventGameVersion::run($event);

        $this->assertNull($event->fresh()->game_version_id);
    }
}
