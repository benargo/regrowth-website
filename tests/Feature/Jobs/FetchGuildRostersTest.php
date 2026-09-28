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

    // ==================== job contract ====================

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

    // ==================== dispatching ====================

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
        Bus::assertNotDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->bypassRateLimit);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_passes_the_rate_limit_bypass_to_each_roster_job(): void
    {
        Bus::fake();

        GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'release_date' => '2024-11-21',
        ]);
        GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ERA,
            'release_date' => '2019-08-26',
        ]);

        (new FetchGuildRosters(bypassRateLimit: true))->handle();

        Bus::assertDispatchedTimes(FetchGuildRoster::class, 2);
        Bus::assertNotDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => ! $job->bypassRateLimit);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_uses_the_latest_release_date_when_versions_share_a_roster(): void
    {
        Bus::fake();

        $older = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'realm' => 'Thunderstrike',
            'guild_name' => 'Regrowth',
            'release_date' => '2024-11-21',
        ]);
        $newer = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'realm' => 'Thunderstrike',
            'guild_name' => 'Regrowth',
            'release_date' => '2026-01-13',
        ]);

        (new FetchGuildRosters)->handle();

        Bus::assertDispatchedTimes(FetchGuildRoster::class, 1);
        Bus::assertDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === $newer->id);
        Bus::assertNotDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === $older->id);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_dispatches_a_roster_job_for_each_guild_sharing_a_namespace_and_realm(): void
    {
        Bus::fake();

        $regrowth = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'realm' => 'Thunderstrike',
            'guild_name' => 'Regrowth',
            'release_date' => '2024-11-21',
        ]);
        $sisterGuild = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'realm' => 'Thunderstrike',
            'guild_name' => 'Sister Guild',
            'release_date' => '2024-11-21',
        ]);

        (new FetchGuildRosters)->handle();

        Bus::assertDispatchedTimes(FetchGuildRoster::class, 2);
        Bus::assertDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === $regrowth->id);
        Bus::assertDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === $sisterGuild->id);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_dispatches_a_roster_job_for_each_realm_sharing_a_namespace(): void
    {
        Bus::fake();

        $nightslayer = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ERA,
            'realm' => 'Nightslayer',
            'guild_name' => 'Regrowth',
            'release_date' => '2019-08-26',
        ]);
        $doomhowl = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ERA,
            'realm' => 'Doomhowl',
            'guild_name' => 'Regrowth',
            'release_date' => '2019-08-26',
        ]);

        (new FetchGuildRosters)->handle();

        Bus::assertDispatchedTimes(FetchGuildRoster::class, 2);
        Bus::assertDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === $nightslayer->id);
        Bus::assertDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === $doomhowl->id);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_dispatches_a_roster_job_for_each_namespace_sharing_a_realm_and_guild(): void
    {
        Bus::fake();

        $anniversary = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'realm' => 'Thunderstrike',
            'guild_name' => 'Regrowth',
            'release_date' => '2024-11-21',
        ]);
        $era = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ERA,
            'realm' => 'Thunderstrike',
            'guild_name' => 'Regrowth',
            'release_date' => '2019-08-26',
        ]);

        (new FetchGuildRosters)->handle();

        Bus::assertDispatchedTimes(FetchGuildRoster::class, 2);
        Bus::assertDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === $anniversary->id);
        Bus::assertDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === $era->id);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_keeps_the_latest_release_within_each_roster(): void
    {
        Bus::fake();

        $olderRegrowth = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'realm' => 'Thunderstrike',
            'guild_name' => 'Regrowth',
            'release_date' => '2024-11-21',
        ]);
        $newerRegrowth = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'realm' => 'Thunderstrike',
            'guild_name' => 'Regrowth',
            'release_date' => '2026-01-13',
        ]);
        $sisterGuild = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'realm' => 'Thunderstrike',
            'guild_name' => 'Sister Guild',
            'release_date' => '2024-11-21',
        ]);

        (new FetchGuildRosters)->handle();

        Bus::assertDispatchedTimes(FetchGuildRoster::class, 2);
        Bus::assertDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === $newerRegrowth->id);
        Bus::assertDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === $sisterGuild->id);
        Bus::assertNotDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === $olderRegrowth->id);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_treats_realm_and_guild_names_that_slug_identically_as_one_roster(): void
    {
        Bus::fake();

        $older = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'realm' => 'Thunderstrike',
            'guild_name' => 'Regrowth',
            'release_date' => '2024-11-21',
        ]);
        $newer = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'realm' => 'thunderstrike',
            'guild_name' => 'regrowth',
            'release_date' => '2026-01-13',
        ]);

        (new FetchGuildRosters)->handle();

        Bus::assertDispatchedTimes(FetchGuildRoster::class, 1);
        Bus::assertDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === $newer->id);
        Bus::assertNotDispatched(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === $older->id);
    }

    // ==================== filtering ====================

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
