<?php

namespace Tests\Feature\Jobs;

use App\Events\Broadcasts\RaceStandingsUpdated;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Jobs\Addon\BuildExportFile;
use App\Jobs\Discord\NotifyDiscordChannel;
use App\Jobs\TrackRaceToSixty;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\RacePlacement;
use App\Notifications\RacePlacementClaimed;
use Carbon\CarbonInterface;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class TrackRaceToSixtyTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([RaceStandingsUpdated::class]);
        Bus::fake([NotifyDiscordChannel::class, BuildExportFile::class]);

        config(['services.discord.channels.announcements' => '111']);

        $this->freezeSecond();

        $this->gameVersion = GameVersion::factory()->create([
            'blizzard_namespace' => BlizzardNamespace::FOREVER,
            'release_date' => now()->subMonth(),
        ]);
    }

    // ==================== job contract ====================

    #[Group('contract')]
    #[Test]
    public function it_has_the_race_tags(): void
    {
        $this->assertSame(['blizzard', 'race-to-60'], (new TrackRaceToSixty)->tags());
    }

    #[Group('contract')]
    #[Test]
    public function it_is_tried_once(): void
    {
        $triesAttributes = (new ReflectionClass(TrackRaceToSixty::class))->getAttributes(Tries::class);

        $this->assertCount(1, $triesAttributes);
        $this->assertSame(1, $triesAttributes[0]->newInstance()->tries);
    }

    #[Group('contract')]
    #[Test]
    public function it_drops_an_overlapping_run_instead_of_releasing_it(): void
    {
        $middleware = collect((new TrackRaceToSixty)->middleware())
            ->first(fn (object $middleware): bool => $middleware instanceof WithoutOverlapping);

        $this->assertNotNull($middleware);
        $this->assertSame('track-race-to-sixty', $middleware->key);
        $this->assertNull($middleware->releaseAfter);
        $this->assertGreaterThan(0, $middleware->expiresAfter);
    }

    #[Group('contract')]
    #[Test]
    public function it_is_scheduled_every_minute_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (ScheduledEvent $event): bool => $event->description === 'track-race-to-sixty');

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    // ==================== polling ====================

    #[Test]
    public function it_polls_only_unplaced_forever_characters_within_five_levels_of_the_highest_unplaced(): void
    {
        $this->placeCharacter($this->createCharacter('Alpha', 40), 1);
        $this->createCharacter('Bravo', 33);
        $this->createCharacter('Charlie', 28);
        $this->createCharacter('Delta', 27);
        Character::factory()
            ->forGameVersion(GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::ERA]))
            ->create(['name' => 'Echo', 'level' => 33]);

        $this->mockGetCharacterProfilesByName([
            'alpha' => ['level' => 40],
            'bravo' => ['level' => 33],
            'charlie' => ['level' => 28],
            'delta' => ['level' => 27],
            'echo' => ['level' => 33],
        ]);

        $this->runJob();

        Saloon::assertSent(fn (Request $request): bool => $this->isProfileRequestFor($request, 'bravo'));
        Saloon::assertSent(fn (Request $request): bool => $this->isProfileRequestFor($request, 'charlie'));
        Saloon::assertNotSent(fn (Request $request): bool => $this->isProfileRequestFor($request, 'alpha'));
        Saloon::assertNotSent(fn (Request $request): bool => $this->isProfileRequestFor($request, 'delta'));
        Saloon::assertNotSent(fn (Request $request): bool => $this->isProfileRequestFor($request, 'echo'));
        Saloon::assertSentCount(3);
    }

    #[Test]
    public function it_sends_short_lived_cache_invalidating_profile_requests(): void
    {
        $this->createCharacter('Alpha', 33);
        $this->mockGetCharacterProfilesByName(['alpha' => ['level' => 33]]);

        $this->runJob();

        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetCharacterProfileRequest
            && $request->cacheExpiryInSeconds() === 50
            && (fn () => $this->invalidateCache)->call($request) === true);
    }

    #[Test]
    public function it_raises_a_stored_level(): void
    {
        $character = $this->createCharacter('Alpha', 33);
        $this->mockGetCharacterProfilesByName(['alpha' => ['level' => 34]]);

        $this->runJob();

        $this->assertSame(34, $character->fresh()->level);
    }

    #[Test]
    public function it_never_lowers_a_stored_level(): void
    {
        $character = $this->createCharacter('Alpha', 34);
        $this->mockGetCharacterProfilesByName(['alpha' => ['level' => 33]]);

        $this->runJob();

        $this->assertSame(34, $character->fresh()->level);
    }

    #[Test]
    public function it_updates_levels_without_firing_the_character_observer(): void
    {
        $character = $this->createCharacter('Alpha', 33);
        $this->mockGetCharacterProfilesByName(['alpha' => ['level' => 34]]);

        $this->runJob();

        $this->assertSame(34, $character->fresh()->level);
        Bus::assertNotDispatched(BuildExportFile::class);
    }

    #[Test]
    public function it_skips_a_failed_lookup_and_continues_without_logging(): void
    {
        Log::spy();

        $this->createCharacter('Alpha', 34);
        $this->createCharacter('Bravo', 34);
        $charlie = $this->createCharacter('Charlie', 34);
        $this->mockGetCharacterProfilesByName([
            'alpha' => 404,
            'bravo' => 500,
            'charlie' => ['level' => 35],
        ]);

        $this->runJob();

        $this->assertSame(35, $charlie->fresh()->level);
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    }

    #[Test]
    public function it_skips_a_malformed_profile_and_still_places_other_finishers(): void
    {
        Log::spy();

        $this->createCharacter('Alpha', 59);
        $bravo = $this->createCharacter('Bravo', 59);
        $this->mockGetCharacterProfilesByName(['bravo' => ['level' => 60]]);
        $this->mockMalformedProfileFor('alpha');

        $this->runJob();

        $this->assertSame(1, $bravo->racePlacement()->firstOrFail()->position);
        Event::assertDispatched(RaceStandingsUpdated::class, fn (RaceStandingsUpdated $event): bool => $event->hasChanges);
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    }

    #[Test]
    public function it_broadcasts_every_tick_with_has_changes_only_when_something_changed(): void
    {
        $this->createCharacter('Alpha', 33);

        $this->mockGetCharacterProfilesByName(['alpha' => ['level' => 33]]);
        $this->runJob();

        $this->assertSame(now()->toIso8601String(), Cache::get('race-to-60:checked-at'));
        $this->travel(1)->minutes();

        $this->mockGetCharacterProfilesByName(['alpha' => ['level' => 34]]);
        $this->runJob();

        $broadcasts = Event::dispatched(RaceStandingsUpdated::class)->map(fn (array $arguments): RaceStandingsUpdated => $arguments[0]);

        $this->assertCount(2, $broadcasts);
        $this->assertFalse($broadcasts[0]->hasChanges);
        $this->assertTrue($broadcasts[1]->hasChanges);
        $this->assertSame(now()->toIso8601String(), Cache::get('race-to-60:checked-at'));
    }

    #[Test]
    public function it_broadcasts_no_changes_without_polling_when_every_character_is_placed(): void
    {
        $this->placeCharacter($this->createCharacter('Alpha', 60), 1);
        $this->mockGetCharacterProfilesByName(['alpha' => ['level' => 60]]);

        $this->runJob();

        Saloon::assertNothingSent();
        Event::assertDispatched(RaceStandingsUpdated::class, fn (RaceStandingsUpdated $event): bool => ! $event->hasChanges);
    }

    #[Test]
    public function it_reads_the_announcements_channel_from_the_injected_config(): void
    {
        $this->createCharacter('Alpha', 59);
        $this->mockGetCharacterProfilesByName(['alpha' => ['level' => 60]]);
        $config = new ConfigRepository(['services' => ['discord' => ['channels' => ['announcements' => '222']]]]);

        $this->applyBlizzardMocks();
        (new TrackRaceToSixty)->handle(app(BlizzardConnector::class), $config);

        Bus::assertDispatched(NotifyDiscordChannel::class, fn (NotifyDiscordChannel $job): bool => $job->channelId === '222');
    }

    #[Test]
    public function it_does_nothing_without_a_forever_game_version(): void
    {
        $this->createCharacter('Alpha', 33);
        $this->gameVersion->delete();
        $this->mockGetCharacterProfilesByName(['alpha' => ['level' => 34]]);

        $this->runJob();

        Event::assertNotDispatched(RaceStandingsUpdated::class);
        Saloon::assertNothingSent();
    }

    // ==================== placements ====================

    #[Test]
    public function it_awards_gold_to_the_first_character_at_sixty_and_queues_an_announcement(): void
    {
        $character = $this->createCharacter('Alpha', 59);
        $this->mockGetCharacterProfilesByName(['alpha' => ['level' => 60]]);

        $this->runJob();

        $placement = $character->racePlacement()->firstOrFail();
        $this->assertSame(1, $placement->position);
        $this->assertTrue($placement->reached_at->equalTo(now()));
        Bus::assertDispatched(NotifyDiscordChannel::class, fn (NotifyDiscordChannel $job): bool => $job->channelId === '111'
            && $this->announcedPlacementId($job) === $placement->id);
    }

    #[Test]
    public function it_awards_silver_and_bronze_in_turn(): void
    {
        $this->placeCharacter($this->createCharacter('Alpha', 60), 1);
        $bravo = $this->createCharacter('Bravo', 59);
        $charlie = $this->createCharacter('Charlie', 59);

        $this->mockGetCharacterProfilesByName(['bravo' => ['level' => 60], 'charlie' => ['level' => 59]]);
        $this->runJob();

        $this->mockGetCharacterProfilesByName(['charlie' => ['level' => 60]]);
        $this->runJob();

        $silver = $bravo->racePlacement()->firstOrFail();
        $bronze = $charlie->racePlacement()->firstOrFail();
        $this->assertSame(2, $silver->position);
        $this->assertSame(3, $bronze->position);
        Bus::assertDispatchedTimes(NotifyDiscordChannel::class, 2);
        Bus::assertDispatched(NotifyDiscordChannel::class, fn (NotifyDiscordChannel $job): bool => $this->announcedPlacementId($job) === $silver->id);
        Bus::assertDispatched(NotifyDiscordChannel::class, fn (NotifyDiscordChannel $job): bool => $this->announcedPlacementId($job) === $bronze->id);
    }

    #[Test]
    public function it_orders_same_tick_finishers_by_last_login(): void
    {
        $alpha = $this->createCharacter('Alpha', 59);
        $bravo = $this->createCharacter('Bravo', 59);
        $this->mockGetCharacterProfilesByName([
            'alpha' => ['level' => 60, 'last_login_timestamp' => 2000],
            'bravo' => ['level' => 60, 'last_login_timestamp' => 1000],
        ]);

        $this->runJob();

        $this->assertSame(1, $bravo->racePlacement()->firstOrFail()->position);
        $this->assertSame(2, $alpha->racePlacement()->firstOrFail()->position);
    }

    #[Test]
    public function it_places_a_character_already_at_sixty_whose_profile_failed_last(): void
    {
        $alpha = $this->createCharacter('Alpha', 60);
        $bravo = $this->createCharacter('Bravo', 59);
        $this->mockGetCharacterProfilesByName([
            'alpha' => 404,
            'bravo' => ['level' => 60, 'last_login_timestamp' => 1000],
        ]);

        $this->runJob();

        $this->assertSame(1, $bravo->racePlacement()->firstOrFail()->position);
        $this->assertSame(2, $alpha->racePlacement()->firstOrFail()->position);
    }

    #[Test]
    public function it_follows_the_chasers_after_gold(): void
    {
        $this->placeCharacter($this->createCharacter('Alpha', 60), 1);
        $this->createCharacter('Bravo', 41);
        $this->createCharacter('Charlie', 36);
        $this->mockGetCharacterProfilesByName(['bravo' => ['level' => 41], 'charlie' => ['level' => 36]]);

        $this->runJob();

        Saloon::assertSent(fn (Request $request): bool => $this->isProfileRequestFor($request, 'bravo'));
        Saloon::assertSent(fn (Request $request): bool => $this->isProfileRequestFor($request, 'charlie'));
    }

    #[Test]
    public function it_records_honourable_mentions_while_closing_without_announcing(): void
    {
        $this->placeCharacter($this->createCharacter('Alpha', 60), 1, now()->subHours(12));
        $this->placeCharacter($this->createCharacter('Bravo', 60), 2, now()->subHours(11));
        $this->placeCharacter($this->createCharacter('Charlie', 60), 3, now()->subHours(10));
        $delta = $this->createCharacter('Delta', 59);
        $this->mockGetCharacterProfilesByName(['delta' => ['level' => 60]]);

        $this->runJob();

        $this->assertSame(4, $delta->racePlacement()->firstOrFail()->position);
        Bus::assertDispatchedTimes(NotifyDiscordChannel::class, 0);
    }

    // ==================== race over ====================

    #[Test]
    public function it_stops_once_forty_eight_hours_have_passed_since_bronze(): void
    {
        $this->placeCharacter($this->createCharacter('Alpha', 60), 1, now()->subHours(50));
        $this->placeCharacter($this->createCharacter('Bravo', 60), 2, now()->subHours(49));
        $this->placeCharacter($this->createCharacter('Charlie', 60), 3, now()->subHours(48));
        $this->createCharacter('Delta', 59);
        $this->mockGetCharacterProfilesByName(['delta' => ['level' => 60]]);

        $this->runJob();

        Saloon::assertNothingSent();
        Event::assertNotDispatched(RaceStandingsUpdated::class);
        $this->assertSame(3, RacePlacement::count());
    }

    // ==================== helpers ====================

    private function runJob(): void
    {
        $this->applyBlizzardMocks();

        (new TrackRaceToSixty)->handle(app(BlizzardConnector::class), app('config'));
    }

    private function createCharacter(string $name, int $level): Character
    {
        return Character::factory()->forGameVersion($this->gameVersion)->create(['name' => $name, 'level' => $level]);
    }

    private function placeCharacter(Character $character, int $position, ?CarbonInterface $reachedAt = null): RacePlacement
    {
        return RacePlacement::factory()->create([
            'game_version_id' => $this->gameVersion->id,
            'character_id' => $character->id,
            'position' => $position,
            'reached_at' => $reachedAt ?? now(),
        ]);
    }

    private function announcedPlacementId(NotifyDiscordChannel $job): ?int
    {
        if (! $job->notification instanceof RacePlacementClaimed) {
            return null;
        }

        return collect($job->notification->mapRelatedModels())
            ->firstWhere('model_type', RacePlacement::class)['model_id'] ?? null;
    }

    /**
     * Serve a profile without a last login timestamp for the named character, keeping the other queued profiles.
     */
    private function mockMalformedProfileFor(string $name): void
    {
        $profiles = $this->pendingBlizzardMocks[GetCharacterProfileRequest::class];

        $this->pendingBlizzardMocks[GetCharacterProfileRequest::class] = function (PendingRequest $pendingRequest) use ($profiles, $name): MockResponse {
            if (! str_ends_with(parse_url($pendingRequest->getUrl(), PHP_URL_PATH) ?: '', "/{$name}")) {
                return $profiles($pendingRequest);
            }

            return MockResponse::make(body: Arr::except([...$this->makeCharacterProfileResponse(), 'level' => 60], 'last_login_timestamp'));
        };
    }

    private function isProfileRequestFor(Request $request, string $name): bool
    {
        return $request instanceof GetCharacterProfileRequest
            && str_ends_with($request->resolveEndpoint(), "/{$name}");
    }
}
