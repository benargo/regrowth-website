<?php

namespace Tests\Feature\Jobs;

use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Jobs\FetchGuildRoster;
use App\Jobs\FetchGuildRosters;
use App\Models\GameVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class FetchGuildRostersTest extends TestCase
{
    use RefreshDatabase;

    #[Group('contract')]
    #[Test]
    public function it_has_the_correct_tags(): void
    {
        $this->assertSame(['blizzard'], (new FetchGuildRosters)->tags());
    }

    #[Group('contract')]
    #[Test]
    public function it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new FetchGuildRosters);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_dispatches_a_roster_job_for_each_distinct_blizzard_namespace(): void
    {
        Bus::fake();

        $anniversary = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'release_date' => '2024-11-21',
        ]);
        $era = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ERA,
            'release_date' => '2019-08-26',
        ]);

        (new FetchGuildRosters)->handle();

        Bus::assertDispatchedTimes(FetchGuildRoster::class, 2);
        Bus::assertDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === $anniversary->id);
        Bus::assertDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === $era->id);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_uses_the_latest_release_date_when_versions_share_a_namespace(): void
    {
        Bus::fake();

        $older = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'release_date' => '2024-11-21',
        ]);
        $newer = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'release_date' => '2026-01-13',
        ]);

        (new FetchGuildRosters)->handle();

        Bus::assertDispatchedTimes(FetchGuildRoster::class, 1);
        Bus::assertDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === $newer->id);
        Bus::assertNotDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === $older->id);
    }

    #[Test]
    public function it_ignores_versions_that_have_not_been_released_yet(): void
    {
        Bus::fake();

        $released = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'release_date' => now()->subMonth(),
        ]);
        GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'release_date' => now()->addMonth(),
        ]);

        (new FetchGuildRosters)->handle();

        Bus::assertDispatchedTimes(FetchGuildRoster::class, 1);
        Bus::assertDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === $released->id);
    }

    #[Test]
    public function it_skips_versions_without_a_blizzard_namespace(): void
    {
        Bus::fake();

        GameVersion::factory()->create(['blizzard_namespace' => null]);

        (new FetchGuildRosters)->handle();

        Bus::assertNotDispatched(FetchGuildRoster::class);
    }

    #[Test]
    public function it_skips_versions_without_a_realm(): void
    {
        Bus::fake();

        $withRealm = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'realm' => 'Thunderstrike',
            'release_date' => '2024-11-21',
        ]);
        GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'realm' => null,
            'release_date' => '2026-01-13',
        ]);

        (new FetchGuildRosters)->handle();

        Bus::assertDispatchedTimes(FetchGuildRoster::class, 1);
        Bus::assertDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === $withRealm->id);
    }
}
