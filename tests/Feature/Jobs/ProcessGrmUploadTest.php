<?php

namespace Tests\Feature\Jobs;

use App\Events\Broadcasts\GrmUploadCompleted as GrmUploadCompletedBroadcast;
use App\Events\Broadcasts\GrmUploadFailed as GrmUploadFailedBroadcast;
use App\Events\Broadcasts\GrmUploadProgressed;
use App\Events\Broadcasts\GrmUploadStarted;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterStatusRequest;
use App\Jobs\FetchGuildRoster;
use App\Jobs\ProcessGrmUpload;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\User;
use App\Notifications\GrmUploadCompleted;
use App\Notifications\GrmUploadFailed;
use App\Services\Discord\Discord;
use App\Services\Discord\Notifications\NotifiableChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use PHPUnit\Framework\Assert as PHPUnit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetClientCredentialsTokenBasicAuthRequest;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('characters')]
class ProcessGrmUploadTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    private User $user;

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([FetchGuildRoster::class]);

        config([
            'services.discord.channels.officer' => '1407688195386114119',
        ]);

        $this->user = User::factory()->create();
        $this->gameVersion = GameVersion::factory()->create();

        // The test queue is sync, so the queued officer notification is delivered
        // inline; stand in for Discord so it never leaves the process.
        $this->mock(Discord::class, function (MockInterface $mock) {
            $mock->shouldReceive('createMessage')->andReturn($this->makeDiscordMessage(id: '9999999999999999999', channelId: '1407688195386114119'));
        });
    }

    // ==================== character creation ====================

    #[Test]
    public function it_creates_character_from_csv_row(): void
    {
        $this->fakeCharacters(['TestChar' => 12345]);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('characters', [
            'id' => 12345,
            'name' => 'TestChar',
            'is_main' => true,
        ]);
    }

    #[Test]
    public function it_associates_character_with_rank(): void
    {
        $this->fakeCharacters(['TestChar' => 12345]);
        $rank = GuildRank::factory()->for($this->gameVersion)->create(['name' => 'Officer']);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Officer', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Alt', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        $character = Character::find(12345);
        $this->assertEquals($rank->id, $character->rank_id);
    }

    #[Test]
    public function it_ignores_a_rank_with_the_same_name_from_another_game_version(): void
    {
        $this->fakeCharacters(['TestChar' => 12345]);
        GuildRank::factory()->for(GameVersion::factory())->create(['name' => 'Officer']);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Officer', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Alt', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        $this->assertNull(Character::find(12345)->rank_id);
    }

    #[Test]
    public function it_sets_is_main_false_for_alt_characters(): void
    {
        $this->fakeCharacters(['AltChar' => 67890]);
        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'AltChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Alt', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('characters', [
            'id' => 67890,
            'is_main' => false,
        ]);
    }

    // ==================== alt linking ====================

    #[Test]
    public function it_creates_character_links_for_main_with_alts(): void
    {
        $this->fakeCharacters([
            'MainChar' => 11111,
            'AltOne' => 22222,
            'AltTwo' => 33333,
        ]);
        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'MainChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => 'AltOne;AltTwo'],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('character_links', [
            'character_id' => 11111,
            'linked_character_id' => 22222,
        ]);

        $this->assertDatabaseHas('character_links', [
            'character_id' => 11111,
            'linked_character_id' => 33333,
        ]);
    }

    #[Test]
    public function it_strips_realm_suffix_from_alt_names(): void
    {
        $this->fakeCharacters([
            'MainChar' => 11111,
            'AltChar' => 22222,
        ]);
        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'MainChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => 'AltChar-Thunderstrike'],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('characters', [
            'id' => 22222,
            'name' => 'AltChar',
        ]);
    }

    #[Test]
    public function it_strips_realm_suffix_with_spaces(): void
    {
        $this->fakeCharacters([
            'MainChar' => 11111,
            'AltChar' => 22222,
        ]);
        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'MainChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => 'AltChar - Wild Growth'],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('characters', [
            'id' => 22222,
            'name' => 'AltChar',
        ]);
    }

    #[Test]
    public function it_uses_opposite_delimiter_for_alt_list(): void
    {
        $this->fakeCharacters([
            'MainChar' => 11111,
            'AltOne' => 22222,
            'AltTwo' => 33333,
        ]);

        // CSV uses semicolon, so alts should be comma-separated
        $job = new ProcessGrmUpload([
            'delimiter' => ';',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'MainChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => 'AltOne,AltTwo'],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        $this->assertDatabaseCount('character_links', 4);
    }

    // ==================== error handling ====================

    #[Test]
    public function it_continues_processing_on_individual_row_error(): void
    {
        $this->fakeCharacters(['SuccessChar' => 99999], notFound: ['FailChar']);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'FailChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
                ['Name' => 'SuccessChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('characters', ['id' => 99999]);
        $this->assertDatabaseMissing('characters', ['name' => 'FailChar']);
    }

    #[Test]
    public function it_sends_completed_notification_with_warnings_when_characters_are_not_found(): void
    {
        $this->fakeCharacters([], notFound: ['FailChar']);

        Notification::fake();

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'FailChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        Notification::assertSentTo(
            NotifiableChannel::stubFromConfig('officer'),
            GrmUploadCompleted::class,
            fn (GrmUploadCompleted $notification) => $notification->processedCount === 0
                && $notification->warningCount === 1,
        );
    }

    #[Test]
    public function it_sends_failed_notification_when_rows_have_errors(): void
    {
        $version = GameVersion::factory()->create(['realm' => null]);

        Notification::fake();

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id, $version->id);

        $job->handle(app(BlizzardConnector::class));

        Notification::assertSentTo(
            NotifiableChannel::stubFromConfig('officer'),
            GrmUploadFailed::class,
            fn (GrmUploadFailed $notification) => $notification->processedCount === 0
                && $notification->errorCount === 1
                && str_starts_with($notification->errors[0], 'TestChar: '),
        );
        Notification::assertNotSentTo(NotifiableChannel::stubFromConfig('officer'), GrmUploadCompleted::class);
    }

    #[Test]
    public function it_sends_completed_notification_when_all_characters_are_skipped(): void
    {
        $this->fakeCharacters(['LowChar' => 99999]);

        Notification::fake();

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'LowChar', 'Rank' => 'Raider', 'Level' => '9', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        Notification::assertSentTo(
            NotifiableChannel::stubFromConfig('officer'),
            GrmUploadCompleted::class,
            fn (GrmUploadCompleted $notification) => $notification->processedCount === 0
                && $notification->skippedCount === 1,
        );
        Bus::assertNotDispatched(FetchGuildRoster::class);
    }

    #[Test]
    public function it_sends_completed_notification_when_characters_are_processed(): void
    {
        $this->fakeCharacters(['TestChar' => 12345]);

        Notification::fake();

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        Notification::assertSentTo(
            NotifiableChannel::stubFromConfig('officer'),
            GrmUploadCompleted::class,
            fn (GrmUploadCompleted $notification) => $notification->processedCount === 1,
        );
    }

    // ==================== data integrity ====================

    #[Test]
    public function it_does_not_create_duplicate_character_links(): void
    {
        $this->fakeCharacters([
            'MainChar' => 11111,
            'AltChar' => 22222,
        ]);

        // Create existing link
        $main = Character::factory()->main()->create(['id' => 11111, 'name' => 'MainChar']);
        $alt = Character::factory()->create(['id' => 22222, 'name' => 'AltChar']);
        $alt->linkedCharacters()->attach($main->id);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'MainChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => 'AltChar'],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        // One link per direction; no duplicates created
        $this->assertDatabaseCount('character_links', 2);
    }

    #[Test]
    public function it_updates_existing_character_data(): void
    {
        $this->fakeCharacters(['TestChar' => 12345]);

        // Create existing character as alt
        Character::factory()->create(['id' => 12345, 'name' => 'TestChar', 'is_main' => false]);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        // Should be updated to main
        $this->assertDatabaseHas('characters', [
            'id' => 12345,
            'is_main' => true,
        ]);
    }

    // ==================== guild roster fetch ====================

    #[Test]
    public function it_dispatches_the_guild_roster_job_once_after_a_successful_batch(): void
    {
        $this->fakeCharacters([
            'CharOne' => 11111,
            'CharTwo' => 22222,
        ]);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'CharOne', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
                ['Name' => 'CharTwo', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Alt', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        Bus::assertDispatchedTimes(FetchGuildRoster::class, 1);
    }

    #[Test]
    public function it_dispatches_the_guild_roster_job_for_the_uploads_game_version(): void
    {
        $this->fakeCharacters(['GoodChar' => 11111]);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'GoodChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        Bus::assertDispatched(
            FetchGuildRoster::class,
            fn (FetchGuildRoster $job) => $job->gameVersionId === $this->gameVersion->id && $job->bypassRateLimit,
        );
    }

    #[Test]
    public function it_does_not_dispatch_the_guild_roster_job_when_no_characters_are_processed(): void
    {
        $this->fakeCharacters([], notFound: ['FailChar']);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'FailChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        Bus::assertNotDispatched(FetchGuildRoster::class);
    }

    #[Test]
    public function it_skips_empty_character_names(): void
    {
        $this->fakeCharacters([]);
        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => '', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
                ['Name' => '   ', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        $this->assertDatabaseCount('characters', 0);
    }

    // ==================== progress broadcasts ====================

    #[Test]
    public function it_broadcasts_started_with_the_total_row_count(): void
    {
        Event::fake([GrmUploadStarted::class, GrmUploadProgressed::class, GrmUploadCompletedBroadcast::class]);

        $this->fakeCharacters(['CharOne' => 11111, 'CharTwo' => 22222]);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'CharOne', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
                ['Name' => 'CharTwo', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Alt', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        Event::assertDispatched(GrmUploadStarted::class, function (GrmUploadStarted $event) {
            return $event->userId === $this->user->id && $event->total === 2;
        });
    }

    #[Test]
    public function it_broadcasts_progress_after_each_row(): void
    {
        Event::fake([GrmUploadStarted::class, GrmUploadProgressed::class, GrmUploadCompletedBroadcast::class]);

        $this->fakeCharacters(['CharOne' => 11111, 'CharTwo' => 22222]);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'CharOne', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
                ['Name' => 'CharTwo', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Alt', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        Event::assertDispatched(GrmUploadProgressed::class, function (GrmUploadProgressed $event) {
            return $event->userId === $this->user->id
                && $event->processedCount === 2
                && $event->total === 2;
        });
    }

    #[Test]
    public function it_broadcasts_completed_with_final_counts(): void
    {
        Event::fake([GrmUploadStarted::class, GrmUploadProgressed::class, GrmUploadCompletedBroadcast::class]);

        $this->fakeCharacters(['CharOne' => 11111]);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'CharOne', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        Event::assertDispatched(GrmUploadCompletedBroadcast::class, function (GrmUploadCompletedBroadcast $event) {
            return $event->userId === $this->user->id
                && $event->processedCount === 1
                && $event->errorCount === 0;
        });
    }

    #[Test]
    public function it_broadcasts_completed_even_when_rows_have_errors(): void
    {
        Event::fake([GrmUploadStarted::class, GrmUploadProgressed::class, GrmUploadCompletedBroadcast::class, GrmUploadFailedBroadcast::class]);

        $this->fakeCharacters(['GoodChar' => 11111], notFound: ['FailChar']);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'GoodChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
                ['Name' => 'FailChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        Event::assertDispatched(GrmUploadCompletedBroadcast::class);
        Event::assertNotDispatched(GrmUploadFailedBroadcast::class);
    }

    #[Test]
    public function it_broadcasts_failed_when_the_job_fails(): void
    {
        Event::fake([GrmUploadFailedBroadcast::class]);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->failed(new \RuntimeException('boom'));

        Event::assertDispatched(GrmUploadFailedBroadcast::class, function (GrmUploadFailedBroadcast $event) {
            return $event->userId === $this->user->id && $event->message === 'boom';
        });
    }

    #[Test]
    public function it_notifies_officers_when_the_job_fails(): void
    {
        Notification::fake();

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->failed(new \RuntimeException('boom'));

        Notification::assertSentTo(
            NotifiableChannel::stubFromConfig('officer'),
            GrmUploadFailed::class,
            fn (GrmUploadFailed $notification) => $notification->exceptionMessage === 'boom',
        );
    }

    // ==================== timestamp integrity ====================

    #[Test]
    public function it_does_not_touch_related_model_timestamps(): void
    {
        // Pre-seed mutually-linked characters to mirror real guild data where alts
        // are already in the DB before the import re-processes them. The job must
        // not update the timestamps of unrelated models (GuildRank) or previously-
        // linked characters.
        $this->fakeCharacters([
            'MainChar' => 11111,
            'AltOne' => 22222,
            'AltTwo' => 33333,
        ]);

        $main = Character::factory()->main()->create(['id' => 11111, 'name' => 'MainChar', 'game_version_id' => $this->gameVersion->id]);
        $altOne = Character::factory()->create(['id' => 22222, 'name' => 'AltOne', 'game_version_id' => $this->gameVersion->id]);
        $altTwo = Character::factory()->create(['id' => 33333, 'name' => 'AltTwo', 'game_version_id' => $this->gameVersion->id]);
        $altOne->linkedCharacters()->attach($main->id);
        $altTwo->linkedCharacters()->attach($main->id);

        $rank = GuildRank::factory()->create(['name' => 'Raider']);

        $originalRankUpdatedAt = $rank->updated_at;
        $originalAltOneUpdatedAt = $altOne->updated_at;
        $originalAltTwoUpdatedAt = $altTwo->updated_at;

        $this->travel(1)->minutes();

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'MainChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => 'AltOne;AltTwo'],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        $rank->refresh();
        $altOne->refresh();
        $altTwo->refresh();

        $this->assertEquals($originalRankUpdatedAt, $rank->updated_at, 'GuildRank should not be touched');
        $this->assertEquals($originalAltOneUpdatedAt, $altOne->updated_at, 'Existing alt characters should not be touched');
        $this->assertEquals($originalAltTwoUpdatedAt, $altTwo->updated_at, 'Existing alt characters should not be touched');
    }

    // ==================== game version ====================

    #[Test]
    public function it_stamps_created_characters_with_the_game_version(): void
    {
        $this->fakeCharacters(['TestChar' => 12345]);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('characters', [
            'id' => 12345,
            'game_version_id' => $this->gameVersion->id,
        ]);
    }

    #[Test]
    public function it_stamps_alt_characters_with_the_same_game_version_as_the_main(): void
    {
        $this->fakeCharacters(['MainChar' => 11111, 'AltOne' => 22222]);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'MainChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => 'AltOne'],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('characters', [
            'id' => 22222,
            'game_version_id' => $this->gameVersion->id,
        ]);
    }

    #[Test]
    public function it_queries_the_blizzard_api_using_the_game_versions_realm(): void
    {
        $version = GameVersion::factory()->create(['realm' => 'stormrage']);
        $this->fakeCharactersOnRealm('stormrage', ['TestChar' => 12345]);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id, $version->id);

        $job->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('characters', ['id' => 12345]);
    }

    // ==================== realm requirement ====================

    #[Test]
    public function it_records_an_error_when_the_game_version_has_no_realm(): void
    {
        $version = GameVersion::factory()->create(['realm' => null]);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id, $version->id);

        $job->handle(app(BlizzardConnector::class));

        $this->assertDatabaseCount('characters', 0);

        Bus::assertNotDispatched(FetchGuildRoster::class);
    }

    #[Test]
    public function it_does_not_process_alts_when_the_game_version_has_no_realm(): void
    {
        // The main character lookup (processRow) requires a realm and fails first,
        // so a missing realm never reaches processAlts() — both call sites share
        // the same $gameVersion->realm, so if the main lookup succeeds, the alt
        // lookup has a realm too. This asserts the alt is never touched when the
        // row fails for lack of a realm.
        $version = GameVersion::factory()->create(['realm' => null]);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'MainChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => 'AltChar'],
            ],
        ], $this->user->id, $version->id);

        $job->handle(app(BlizzardConnector::class));

        $this->assertDatabaseMissing('characters', ['name' => 'MainChar']);
        $this->assertDatabaseMissing('characters', ['name' => 'AltChar']);
        $this->assertDatabaseCount('character_links', 0);
    }

    // ==================== cross-version isolation ====================

    #[Test]
    public function a_character_created_under_one_game_version_keeps_its_stamp_when_reprocessed_under_another(): void
    {
        $this->fakeCharacters(['TestChar' => 12345]);
        $versionA = GameVersion::factory()->create();
        $versionB = GameVersion::factory()->create();

        $this->mock(Discord::class, function (MockInterface $mock) {
            $mock->shouldReceive('createMessage')
                ->andReturnUsing(fn () => $this->makeDiscordMessage(id: (string) fake()->unique()->numerify('99999999999999#####'), channelId: '1407688195386114119'));
        });

        $firstRun = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id, $versionA->id);
        $firstRun->handle(app(BlizzardConnector::class));

        $secondRun = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id, $versionB->id);
        $secondRun->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('characters', [
            'id' => 12345,
            'game_version_id' => $versionB->id,
        ]);
    }

    // ==================== accented names ====================

    #[Test]
    #[Group('blizzard-integration')]
    public function it_resolves_names_that_differ_only_by_an_accent_to_their_own_characters(): void
    {
        $this->fakeCharacters([
            'Izepo' => 11111,
            'Ízepo' => 22222,
            'Ozona' => 33333,
            'Ozonà' => 44444,
            'Ozòna' => 55555,
        ]);
        Notification::fake();

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'Izepo', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => 'Ízepo'],
                ['Name' => 'Ozona', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => 'Ozonà;Ozòna'],
            ],
        ], $this->user->id, $this->gameVersion->id);

        $job->handle(app(BlizzardConnector::class));

        $this->assertSame(
            [11111 => 'Izepo', 22222 => 'Ízepo', 33333 => 'Ozona', 44444 => 'Ozonà', 55555 => 'Ozòna'],
            Character::orderBy('id')->pluck('name', 'id')->all(),
        );
        $this->assertDatabaseHas('character_links', ['character_id' => 11111, 'linked_character_id' => 22222]);
        $this->assertDatabaseHas('character_links', ['character_id' => 33333, 'linked_character_id' => 44444]);
        $this->assertDatabaseHas('character_links', ['character_id' => 33333, 'linked_character_id' => 55555]);
        Notification::assertSentTo(NotifiableChannel::stubFromConfig('officer'), GrmUploadCompleted::class);
        Notification::assertNotSentTo(NotifiableChannel::stubFromConfig('officer'), GrmUploadFailed::class);
    }

    // ==================== helpers ====================

    /**
     * Fake the Blizzard character status/profile endpoints.
     *
     * Blizzard expects the lowercased name (diacritics kept) in the request path, so we resolve the
     * slug back to an ID from the supplied map. Names listed in $notFound
     * return a translated 404 (CharacterNotFoundException).
     *
     * @param  array<string, int>  $characterMap
     * @param  array<int, string>  $notFound
     */
    protected function fakeCharacters(array $characterMap, array $notFound = []): void
    {
        $idBySlug = [];
        foreach ($characterMap as $name => $id) {
            $idBySlug[Str::lower($name)] = $id;
        }

        $notFoundSlugs = array_map(fn (string $name) => Str::lower($name), $notFound);

        $resolve = function (PendingRequest $pendingRequest) use ($idBySlug, $notFoundSlugs): MockResponse {
            $path = parse_url($pendingRequest->getUrl(), PHP_URL_PATH) ?: '';
            // /profile/wow/character/{realm}/{slug}[/status]
            $segments = explode('/', trim($path, '/'));
            $slug = rawurldecode($segments[4] ?? '');

            if (in_array($slug, $notFoundSlugs, true)) {
                return MockResponse::make(
                    body: ['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'],
                    status: 404,
                );
            }

            $id = $idBySlug[$slug] ?? 0;

            return MockResponse::make(body: [
                'id' => $id,
                'name' => $slug,
                'is_valid' => true,
                'gender' => ['type' => 'MALE', 'name' => 'Male'],
                'faction' => ['type' => 'ALLIANCE', 'name' => 'Alliance'],
                'race' => ['key' => ['href' => 'https://example.test/race/1'], 'name' => 'Human', 'id' => 1],
                'character_class' => ['key' => ['href' => 'https://example.test/class/1'], 'name' => 'Warrior', 'id' => 1],
                'realm' => ['key' => ['href' => 'https://example.test/realm/1'], 'name' => 'Thunderstrike', 'id' => 1],
                'level' => 70,
                'last_login_timestamp' => 0,
                'average_item_level' => 0,
                'equipped_item_level' => 0,
            ], status: 200);
        };

        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => MockResponse::make(body: [
                'access_token' => 'test_token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], status: 200),
            GetCharacterStatusRequest::class => $resolve,
            GetCharacterProfileRequest::class => $resolve,
        ]);
    }

    /**
     * Like fakeCharacters(), but asserts every request's realm segment matches
     * the given slug, failing the test if a request goes to the wrong realm.
     *
     * @param  array<string, int>  $characterMap
     */
    protected function fakeCharactersOnRealm(string $realm, array $characterMap): void
    {
        $realmSlug = Str::slug($realm);
        $idBySlug = [];
        foreach ($characterMap as $name => $id) {
            $idBySlug[Str::lower($name)] = $id;
        }

        $resolve = function (PendingRequest $pendingRequest) use ($realm, $realmSlug, $idBySlug): MockResponse {
            $path = parse_url($pendingRequest->getUrl(), PHP_URL_PATH) ?: '';
            $segments = explode('/', trim($path, '/'));
            $requestRealm = $segments[3] ?? '';
            $slug = rawurldecode($segments[4] ?? '');

            PHPUnit::assertSame($realmSlug, $requestRealm, "Expected request against realm '{$realmSlug}', got '{$requestRealm}'.");

            $id = $idBySlug[$slug] ?? 0;

            return MockResponse::make(body: [
                'id' => $id,
                'name' => $slug,
                'is_valid' => true,
                'gender' => ['type' => 'MALE', 'name' => 'Male'],
                'faction' => ['type' => 'ALLIANCE', 'name' => 'Alliance'],
                'race' => ['key' => ['href' => 'https://example.test/race/1'], 'name' => 'Human', 'id' => 1],
                'character_class' => ['key' => ['href' => 'https://example.test/class/1'], 'name' => 'Warrior', 'id' => 1],
                'realm' => ['key' => ['href' => 'https://example.test/realm/1'], 'name' => $realm, 'id' => 1],
                'level' => 70,
                'last_login_timestamp' => 0,
                'average_item_level' => 0,
                'equipped_item_level' => 0,
            ], status: 200);
        };

        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => MockResponse::make(body: [
                'access_token' => 'test_token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], status: 200),
            GetCharacterStatusRequest::class => $resolve,
            GetCharacterProfileRequest::class => $resolve,
        ]);
    }
}
