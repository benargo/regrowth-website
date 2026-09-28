<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\FetchGuildRoster;
use App\Jobs\FetchGuildRosters;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class FetchGuildRosterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_dispatches_fetch_guild_rosters_without_an_option(): void
    {
        Bus::fake([FetchGuildRoster::class, FetchGuildRosters::class]);

        $this->artisan('fetch:blizzard-roster')
            ->expectsOutput('Guild roster refresh queued for each game version.')
            ->assertSuccessful();

        Bus::assertDispatchedSync(FetchGuildRosters::class, fn (FetchGuildRosters $job) => $job->bypassRateLimit);
    }

    #[Test]
    public function it_dispatches_the_roster_job_for_the_given_game_version(): void
    {
        Bus::fake([FetchGuildRoster::class, FetchGuildRosters::class]);

        $gameVersion = GameVersion::factory()->create();

        $this->artisan('fetch:blizzard-roster', ['--game-version' => $gameVersion->id])
            ->expectsOutput('Guild roster refreshed.')
            ->assertSuccessful();

        Bus::assertDispatchedSync(
            FetchGuildRoster::class,
            fn (FetchGuildRoster $job) => $job->gameVersionId === $gameVersion->id && $job->bypassRateLimit,
        );
        Bus::assertNotDispatchedSync(FetchGuildRosters::class);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_non_numeric_game_version(): void
    {
        Bus::fake([FetchGuildRoster::class, FetchGuildRosters::class]);

        $this->artisan('fetch:blizzard-roster', ['--game-version' => 'abc'])
            ->expectsOutput('The --game-version option must be a numeric game version ID.')
            ->assertFailed();

        Bus::assertNothingDispatched();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_unknown_game_version(): void
    {
        Bus::fake([FetchGuildRoster::class, FetchGuildRosters::class]);

        $this->artisan('fetch:blizzard-roster', ['--game-version' => 999])
            ->expectsOutput('Game version 999 not found.')
            ->assertFailed();

        Bus::assertNothingDispatched();
    }

    #[Group('validation')]
    #[Test]
    #[TestWith(['realm'])]
    #[TestWith(['blizzard_namespace'])]
    public function it_rejects_a_game_version_without_a_realm_or_blizzard_namespace(string $attribute): void
    {
        Bus::fake([FetchGuildRoster::class, FetchGuildRosters::class]);

        $gameVersion = GameVersion::factory()->create([$attribute => null]);

        $this->artisan('fetch:blizzard-roster', ['--game-version' => $gameVersion->id])
            ->expectsOutput("Game version {$gameVersion->id} has no realm or Blizzard namespace configured.")
            ->assertFailed();

        Bus::assertNothingDispatched();
    }
}
