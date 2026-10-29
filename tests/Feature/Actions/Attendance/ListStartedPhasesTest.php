<?php

namespace Tests\Feature\Actions\Attendance;

use App\Actions\Attendance\ListStartedPhases;
use App\Models\GameVersion;
use App\Models\Phase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsAttendanceFixtures;
use Tests\TestCase;

#[Group('raiding')]
class ListStartedPhasesTest extends TestCase
{
    use BuildsAttendanceFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
    }

    #[Test]
    public function it_lists_the_versions_started_phases_newest_first(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $older = Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => now()->subDays(30)]);
        $newer = Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => now()->subDays(7)]);
        Phase::factory()->forGameVersion($gameVersion)->upcoming()->create();
        Phase::factory()->forGameVersion($gameVersion)->unscheduled()->create();
        Phase::factory()->forGameVersion(GameVersion::factory()->create())->create(['start_date' => now()->subDay()]);

        $this->assertSame([$newer->id, $older->id], ListStartedPhases::run($gameVersion)->modelKeys());
    }

    #[Test]
    public function it_selects_only_the_phase_id_and_start_date(): void
    {
        $gameVersion = GameVersion::factory()->create();
        Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => now()->subDay()]);

        $this->assertSame(['id', 'start_date'], array_keys(ListStartedPhases::run($gameVersion)->first()->getAttributes()));
    }

    #[Test]
    public function it_caches_each_versions_phases_separately(): void
    {
        $first = GameVersion::factory()->create();
        $second = GameVersion::factory()->create();
        $phase = Phase::factory()->forGameVersion($second)->create(['start_date' => now()->subDay()]);

        ListStartedPhases::run($first);

        $this->assertSame([$phase->id], ListStartedPhases::run($second)->modelKeys());
        $this->assertTrue(Cache::tags(['attendance'])->has("phases:where_started:{$first->id}"));
    }

    #[Test]
    public function it_keeps_phase_start_dates_in_the_app_timezone_when_read_and_cached(): void
    {
        $gameVersion = GameVersion::factory()->create();
        Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => '2026-10-01 00:00:00']);

        $this->assertSame('2026-10-01', ListStartedPhases::run($gameVersion)->first()->start_date->toDateString());
        $this->assertSame('2026-10-01', ListStartedPhases::run($gameVersion)->first()->start_date->toDateString());
    }

    #[Test]
    public function it_returns_phases_with_local_start_dates_from_a_serializing_cache_store(): void
    {
        config(['cache.stores.array.serialize' => true]);
        Cache::forgetDriver('array');

        $gameVersion = GameVersion::factory()->create();
        Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => '2026-10-01 00:00:00']);

        ListStartedPhases::run($gameVersion);
        $phase = ListStartedPhases::run($gameVersion)->first();

        $this->assertInstanceOf(Phase::class, $phase);
        $this->assertSame('2026-10-01', $phase->start_date->toDateString());
    }
}
