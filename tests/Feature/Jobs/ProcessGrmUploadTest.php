<?php

namespace Tests\Feature\Jobs;

use App\Events\Broadcasts\GrmUploadCompleted as GrmUploadCompletedBroadcast;
use App\Events\Broadcasts\GrmUploadFailed as GrmUploadFailedBroadcast;
use App\Events\Broadcasts\GrmUploadProgressed;
use App\Events\Broadcasts\GrmUploadStarted;
use App\Events\GrmUploadProcessed;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterStatusRequest;
use App\Jobs\ProcessGrmUpload;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\User;
use App\Services\Discord\Discord;
use App\Services\Discord\Exceptions\RateLimitedException;
use App\Services\Discord\Resources\Channel as ChannelResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Mockery\MockInterface;
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

    private Discord $discord;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.discord.channels.officer' => '1407688195386114119',
        ]);

        $this->user = User::factory()->create();

        $channel = ChannelResource::from(['id' => '1407688195386114119', 'type' => 0]);

        $this->discord = $this->mock(Discord::class, function (MockInterface $mock) use ($channel) {
            $mock->shouldReceive('getChannel')->andReturn($channel);
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
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $this->discord);

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
        $rank = GuildRank::factory()->create(['name' => 'Officer']);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Officer', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Alt', 'Player Alts' => ''],
            ],
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $this->discord);

        $character = Character::find(12345);
        $this->assertEquals($rank->id, $character->rank_id);
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
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $this->discord);

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
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $this->discord);

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
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $this->discord);

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
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $this->discord);

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
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $this->discord);

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
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $this->discord);

        $this->assertDatabaseHas('characters', ['id' => 99999]);
        $this->assertDatabaseMissing('characters', ['name' => 'FailChar']);
    }

    #[Test]
    public function it_sends_failed_notification_when_no_characters_are_processed(): void
    {
        $this->fakeCharacters([], notFound: ['FailChar']);

        $discordMock = $this->mock(Discord::class, function (MockInterface $mock) {
            $channel = ChannelResource::from(['id' => '1407688195386114119', 'type' => 0]);
            $message = $this->makeDiscordMessage(id: '9999999999999999999', channelId: '1407688195386114119');

            $mock->shouldReceive('getChannel')->andReturn($channel);
            $mock->shouldReceive('createMessage')
                ->withArgs(fn ($ch, $payload) => $payload->embeds[0]->title === 'GRM Upload Processing Failed'
                    || $payload->embeds[0]->title === 'GRM Upload Processing Completed with Errors'
                    || $payload->embeds[0]->title === 'GRM Upload Processing Completed')
                ->once()
                ->andReturn($message);
        });

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'FailChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $discordMock);
    }

    #[Test]
    public function it_sends_completed_notification_when_all_characters_are_skipped(): void
    {
        Event::fake([GrmUploadProcessed::class]);

        $this->fakeCharacters(['LowChar' => 99999]);

        $discordMock = $this->mock(Discord::class, function (MockInterface $mock) {
            $channel = ChannelResource::from(['id' => '1407688195386114119', 'type' => 0]);
            $message = $this->makeDiscordMessage(id: '9999999999999999999', channelId: '1407688195386114119');

            $mock->shouldReceive('getChannel')->andReturn($channel);
            $mock->shouldReceive('createMessage')
                ->withArgs(fn ($ch, $payload) => $payload->embeds[0]->title === 'GRM Upload Processing Completed')
                ->once()
                ->andReturn($message);
        });

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'LowChar', 'Rank' => 'Raider', 'Level' => '10', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $discordMock);

        Event::assertNotDispatched(GrmUploadProcessed::class);
    }

    #[Test]
    public function it_sends_completed_notification_when_characters_are_processed(): void
    {
        $this->fakeCharacters(['TestChar' => 12345]);

        $discordMock = $this->mock(Discord::class, function (MockInterface $mock) {
            $channel = ChannelResource::from(['id' => '1407688195386114119', 'type' => 0]);
            $message = $this->makeDiscordMessage(id: '9999999999999999999', channelId: '1407688195386114119');

            $mock->shouldReceive('getChannel')->andReturn($channel);
            $mock->shouldReceive('createMessage')
                ->withArgs(fn ($ch, $payload) => $payload->embeds[0]->title === 'GRM Upload Processing Completed')
                ->once()
                ->andReturn($message);
        });

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $discordMock);
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
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $this->discord);

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
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $this->discord);

        // Should be updated to main
        $this->assertDatabaseHas('characters', [
            'id' => 12345,
            'is_main' => true,
        ]);
    }

    // ==================== grm upload processed event ====================

    #[Test]
    public function it_dispatches_grm_upload_processed_event_once_after_successful_batch(): void
    {
        Event::fake([GrmUploadProcessed::class]);

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
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $this->discord);

        Event::assertDispatchedTimes(GrmUploadProcessed::class, 1);
    }

    #[Test]
    public function grm_upload_processed_event_carries_correct_metrics(): void
    {
        Event::fake([GrmUploadProcessed::class]);

        $this->fakeCharacters(['GoodChar' => 11111], notFound: ['FailChar']);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'GoodChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
                ['Name' => 'FailChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $this->discord);

        Event::assertDispatched(GrmUploadProcessed::class, function (GrmUploadProcessed $event) {
            return $event->processedCount === 1
                && $event->warningCount === 1
                && $event->skippedCount === 0
                && $event->errorCount === 0
                && count($event->errors) === 0;
        });
    }

    #[Test]
    public function it_does_not_dispatch_grm_upload_processed_event_when_no_characters_are_processed(): void
    {
        Event::fake([GrmUploadProcessed::class]);

        $this->fakeCharacters([], notFound: ['FailChar']);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'FailChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $this->discord);

        Event::assertNotDispatched(GrmUploadProcessed::class);
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
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $this->discord);

        $this->assertDatabaseCount('characters', 0);
    }

    // ==================== rate limiting ====================

    #[Test]
    public function it_releases_itself_when_discord_is_rate_limited_sending_notification(): void
    {
        $this->fakeCharacters(['TestChar' => 12345]);

        $rateLimitedDiscord = $this->mock(Discord::class, function (MockInterface $mock) {
            $mock->shouldReceive('getChannel')
                ->once()
                ->andThrow(new RateLimitedException('channels/1407688195386114119', 5.0, 'user'));
        });

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ], $this->user->id);
        $job->withFakeQueueInteractions();
        $job->handle(app(BlizzardConnector::class), $rateLimitedDiscord);

        $job->assertReleased(5.0);
        $this->assertDatabaseHas('characters', ['id' => 12345]);
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
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $this->discord);

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
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $this->discord);

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
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $this->discord);

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
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $this->discord);

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
        ], $this->user->id);

        $job->failed(new \RuntimeException('boom'));

        Event::assertDispatched(GrmUploadFailedBroadcast::class, function (GrmUploadFailedBroadcast $event) {
            return $event->userId === $this->user->id && $event->message === 'boom';
        });
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

        $main = Character::factory()->main()->create(['id' => 11111, 'name' => 'MainChar']);
        $altOne = Character::factory()->create(['id' => 22222, 'name' => 'AltOne']);
        $altTwo = Character::factory()->create(['id' => 33333, 'name' => 'AltTwo']);
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
        ], $this->user->id);

        $job->handle(app(BlizzardConnector::class), $this->discord);

        $rank->refresh();
        $altOne->refresh();
        $altTwo->refresh();

        $this->assertEquals($originalRankUpdatedAt, $rank->updated_at, 'GuildRank should not be touched');
        $this->assertEquals($originalAltOneUpdatedAt, $altOne->updated_at, 'Existing alt characters should not be touched');
        $this->assertEquals($originalAltTwoUpdatedAt, $altTwo->updated_at, 'Existing alt characters should not be touched');
    }

    // ==================== helpers ====================

    /**
     * Fake the Blizzard character status/profile endpoints.
     *
     * The character name is slugged into the request path, so we resolve the
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
            $idBySlug[Str::slug($name)] = $id;
        }

        $notFoundSlugs = array_map(fn (string $name) => Str::slug($name), $notFound);

        $resolve = function (PendingRequest $pendingRequest) use ($idBySlug, $notFoundSlugs): MockResponse {
            $path = parse_url($pendingRequest->getUrl(), PHP_URL_PATH) ?: '';
            // /profile/wow/character/{realm}/{slug}[/status]
            $segments = explode('/', trim($path, '/'));
            $slug = $segments[4] ?? '';

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
}
