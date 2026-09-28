<?php

namespace Tests\Feature\Jobs;

use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Jobs\FetchGuildRoster;
use App\Jobs\RefreshGuildRosterAfterEditing;
use App\Models\GameVersion;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
#[Group('platform')]
class RefreshGuildRosterAfterEditingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    #[Group('contract')]
    #[Test]
    public function it_is_unique_until_it_starts_processing(): void
    {
        $this->assertInstanceOf(ShouldBeUniqueUntilProcessing::class, new RefreshGuildRosterAfterEditing($this->fetchableGameVersion()));
    }

    #[Group('happy-path')]
    #[Test]
    public function it_fetches_the_game_versions_roster_bypassing_the_rate_limit_when_nobody_is_editing(): void
    {
        $gameVersion = $this->fetchableGameVersion();

        (new RefreshGuildRosterAfterEditing($gameVersion))->handle();

        Queue::assertPushed(
            FetchGuildRoster::class,
            fn (FetchGuildRoster $job): bool => $job->gameVersionId === $gameVersion->id && $job->bypassRateLimit,
        );
        Queue::assertNotPushed(RefreshGuildRosterAfterEditing::class);
    }

    #[Test]
    public function it_checks_again_later_while_the_game_version_is_being_edited(): void
    {
        $gameVersion = $this->fetchableGameVersion();
        $gameVersion->acquireEditLock(User::factory()->officer()->create());

        (new RefreshGuildRosterAfterEditing($gameVersion))->handle();

        Queue::assertNotPushed(FetchGuildRoster::class);
        Queue::assertPushed(
            RefreshGuildRosterAfterEditing::class,
            fn (RefreshGuildRosterAfterEditing $job): bool => $job->gameVersion->is($gameVersion)
                && $job->delay === RefreshGuildRosterAfterEditing::RECHECK_SECONDS,
        );
    }

    #[Test]
    public function it_fetches_the_roster_once_the_edit_lock_has_expired(): void
    {
        $gameVersion = $this->fetchableGameVersion();
        $gameVersion->acquireEditLock(User::factory()->officer()->create());
        $this->travel(GameVersion::EDIT_LOCK_SECONDS + 1)->seconds();

        (new RefreshGuildRosterAfterEditing($gameVersion))->handle();

        Queue::assertPushed(FetchGuildRoster::class, 1);
    }

    #[Test]
    public function it_skips_a_game_version_that_does_not_own_a_current_roster(): void
    {
        $older = $this->fetchableGameVersion(['release_date' => now()->subYear()]);
        $this->fetchableGameVersion(['release_date' => now()->subMonth()]);

        (new RefreshGuildRosterAfterEditing($older))->handle();

        Queue::assertNotPushed(FetchGuildRoster::class);
        Queue::assertNotPushed(RefreshGuildRosterAfterEditing::class);
    }

    #[Test]
    public function it_queues_only_one_refresh_per_game_version(): void
    {
        $classic = $this->fetchableGameVersion();
        $anniversary = $this->fetchableGameVersion(['guild_name' => 'Another Guild']);

        RefreshGuildRosterAfterEditing::schedule($classic);
        RefreshGuildRosterAfterEditing::schedule($classic);
        RefreshGuildRosterAfterEditing::schedule($anniversary);

        Queue::assertPushed(RefreshGuildRosterAfterEditing::class, 2);
    }

    #[Test]
    public function it_queues_another_refresh_once_the_pending_ones_unique_lock_has_expired(): void
    {
        $gameVersion = $this->fetchableGameVersion();

        RefreshGuildRosterAfterEditing::schedule($gameVersion);
        $this->travel((new RefreshGuildRosterAfterEditing($gameVersion))->uniqueFor + 1)->seconds();
        RefreshGuildRosterAfterEditing::schedule($gameVersion);

        Queue::assertPushed(RefreshGuildRosterAfterEditing::class, 2);
    }

    #[Test]
    public function a_queued_refresh_that_finds_the_game_version_being_edited_queues_its_own_recheck(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake()->except(RefreshGuildRosterAfterEditing::class);
        $gameVersion = $this->fetchableGameVersion();
        $gameVersion->acquireEditLock(User::factory()->officer()->create());

        RefreshGuildRosterAfterEditing::schedule($gameVersion);
        $this->travel(RefreshGuildRosterAfterEditing::RECHECK_SECONDS + 1)->seconds();

        $queue = new DatabaseQueue($this->app['db']->connection(), 'jobs');
        $queue->setContainer($this->app);
        $queue->setConnectionName('database');
        $queue->pop()->fire();

        Queue::assertNotPushed(FetchGuildRoster::class);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame(
            RefreshGuildRosterAfterEditing::class,
            json_decode($this->app['db']->table('jobs')->value('payload'), true)['displayName'],
        );
    }

    /**
     * The factory randomises the release date and namespace, so pin a version
     * that owns a current roster.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function fetchableGameVersion(array $overrides = []): GameVersion
    {
        return GameVersion::factory()->create([
            'realm' => 'Thunderstrike',
            'guild_name' => 'Regrowth',
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'release_date' => now()->subMonth(),
            ...$overrides,
        ]);
    }
}
