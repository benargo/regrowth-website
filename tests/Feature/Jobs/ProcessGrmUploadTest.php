<?php

namespace Tests\Feature\Jobs;

use App\Events\GrmUploadProcessed;
use App\Jobs\ProcessGrmUpload;
use App\Models\Character;
use App\Models\GuildRank;
use App\Services\Blizzard\BlizzardService;
use App\Services\Discord\Discord;
use App\Services\Discord\Enums\MessageType;
use App\Services\Discord\Resources\Channel as ChannelResource;
use App\Services\Discord\Resources\Message as MessageResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessGrmUploadTest extends TestCase
{
    use RefreshDatabase;

    private Discord $discord;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.discord.channels.officer' => '1407688195386114119',
        ]);

        $channel = ChannelResource::from(['id' => '1407688195386114119', 'type' => 0]);

        $this->discord = $this->mock(Discord::class, function (MockInterface $mock) use ($channel) {
            $mock->shouldReceive('getChannel')->andReturn($channel);
            $mock->shouldReceive('createMessage')->andReturn($this->makeMessage());
        });
    }

    #[Test]
    public function it_creates_character_from_csv_row(): void
    {
        $this->mockCharacterService(['TestChar' => 12345]);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ]);

        $job->handle(app(BlizzardService::class), $this->discord);

        $this->assertDatabaseHas('characters', [
            'id' => 12345,
            'name' => 'TestChar',
            'is_main' => true,
        ]);
    }

    #[Test]
    public function it_associates_character_with_rank(): void
    {
        $this->mockCharacterService(['TestChar' => 12345]);
        $rank = GuildRank::factory()->create(['name' => 'Officer']);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Officer', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Alt', 'Player Alts' => ''],
            ],
        ]);

        $job->handle(app(BlizzardService::class), $this->discord);

        $character = Character::find(12345);
        $this->assertEquals($rank->id, $character->rank_id);
    }

    #[Test]
    public function it_sets_is_main_false_for_alt_characters(): void
    {
        $this->mockCharacterService(['AltChar' => 67890]);
        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'AltChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Alt', 'Player Alts' => ''],
            ],
        ]);

        $job->handle(app(BlizzardService::class), $this->discord);

        $this->assertDatabaseHas('characters', [
            'id' => 67890,
            'is_main' => false,
        ]);
    }

    #[Test]
    public function it_creates_character_links_for_main_with_alts(): void
    {
        $this->mockCharacterService([
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
        ]);

        $job->handle(app(BlizzardService::class), $this->discord);

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
        $this->mockCharacterService([
            'MainChar' => 11111,
            'AltChar' => 22222,
        ]);
        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'MainChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => 'AltChar-Thunderstrike'],
            ],
        ]);

        $job->handle(app(BlizzardService::class), $this->discord);

        $this->assertDatabaseHas('characters', [
            'id' => 22222,
            'name' => 'AltChar',
        ]);
    }

    #[Test]
    public function it_strips_realm_suffix_with_spaces(): void
    {
        $this->mockCharacterService([
            'MainChar' => 11111,
            'AltChar' => 22222,
        ]);
        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'MainChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => 'AltChar - Wild Growth'],
            ],
        ]);

        $job->handle(app(BlizzardService::class), $this->discord);

        $this->assertDatabaseHas('characters', [
            'id' => 22222,
            'name' => 'AltChar',
        ]);
    }

    #[Test]
    public function it_uses_opposite_delimiter_for_alt_list(): void
    {
        $this->mockCharacterService([
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
        ]);

        $job->handle(app(BlizzardService::class), $this->discord);

        $this->assertDatabaseCount('character_links', 2);
    }

    #[Test]
    public function it_continues_processing_on_individual_row_error(): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getCharacterStatus')
                ->with('FailChar')
                ->andThrow(new \RuntimeException('Character not found'));

            $mock->shouldReceive('getCharacterStatus')
                ->with('SuccessChar')
                ->andReturn(['id' => 99999]);
        });
        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'FailChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
                ['Name' => 'SuccessChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ]);

        $job->handle(app(BlizzardService::class), $this->discord);

        $this->assertDatabaseHas('characters', ['id' => 99999]);
        $this->assertDatabaseMissing('characters', ['name' => 'FailChar']);
    }

    #[Test]
    public function it_sends_failed_notification_when_no_characters_are_processed(): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getCharacterStatus')
                ->andThrow(new \RuntimeException('Character not found'));
        });

        $discordMock = $this->mock(Discord::class, function (MockInterface $mock) {
            $channel = ChannelResource::from(['id' => '1407688195386114119', 'type' => 0]);
            $message = $this->makeMessage();

            $mock->shouldReceive('getChannel')->andReturn($channel);
            $mock->shouldReceive('createMessage')
                ->withArgs(fn ($ch, $payload) => $payload->embeds[0]->title === 'GRM Upload Processing Failed'
                    || $payload->embeds[0]->title === 'GRM Upload Processing Completed with Errors')
                ->once()
                ->andReturn($message);
        });

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'FailChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ]);

        $job->handle(app(BlizzardService::class), $discordMock);
    }

    #[Test]
    public function it_sends_completed_notification_when_all_characters_are_skipped(): void
    {
        Event::fake([GrmUploadProcessed::class]);

        $this->mockCharacterService(['LowChar' => 99999]);

        $discordMock = $this->mock(Discord::class, function (MockInterface $mock) {
            $channel = ChannelResource::from(['id' => '1407688195386114119', 'type' => 0]);
            $message = $this->makeMessage();

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
        ]);

        $job->handle(app(BlizzardService::class), $discordMock);

        Event::assertNotDispatched(GrmUploadProcessed::class);
    }

    #[Test]
    public function it_sends_completed_notification_when_characters_are_processed(): void
    {
        $this->mockCharacterService(['TestChar' => 12345]);

        $discordMock = $this->mock(Discord::class, function (MockInterface $mock) {
            $channel = ChannelResource::from(['id' => '1407688195386114119', 'type' => 0]);
            $message = $this->makeMessage();

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
        ]);

        $job->handle(app(BlizzardService::class), $discordMock);
    }

    #[Test]
    public function it_does_not_create_duplicate_character_links(): void
    {
        $this->mockCharacterService([
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
        ]);

        $job->handle(app(BlizzardService::class), $this->discord);

        // Should still only have one link
        $this->assertDatabaseCount('character_links', 1);
    }

    #[Test]
    public function it_updates_existing_character_data(): void
    {
        $this->mockCharacterService(['TestChar' => 12345]);

        // Create existing character as alt
        Character::factory()->create(['id' => 12345, 'name' => 'TestChar', 'is_main' => false]);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ]);

        $job->handle(app(BlizzardService::class), $this->discord);

        // Should be updated to main
        $this->assertDatabaseHas('characters', [
            'id' => 12345,
            'is_main' => true,
        ]);
    }

    #[Test]
    public function it_dispatches_grm_upload_processed_event_once_after_successful_batch(): void
    {
        Event::fake([GrmUploadProcessed::class]);

        $this->mockCharacterService([
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
        ]);

        $job->handle(app(BlizzardService::class), $this->discord);

        Event::assertDispatchedTimes(GrmUploadProcessed::class, 1);
    }

    #[Test]
    public function grm_upload_processed_event_carries_correct_metrics(): void
    {
        Event::fake([GrmUploadProcessed::class]);

        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getCharacterStatus')
                ->with('GoodChar')
                ->andReturn(['id' => 11111]);

            $mock->shouldReceive('getCharacterStatus')
                ->with('FailChar')
                ->andThrow(new \RuntimeException('API error'));
        });

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'GoodChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
                ['Name' => 'FailChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ]);

        $job->handle(app(BlizzardService::class), $this->discord);

        Event::assertDispatched(GrmUploadProcessed::class, function (GrmUploadProcessed $event) {
            return $event->processedCount === 1
                && $event->errorCount === 1
                && $event->skippedCount === 0
                && $event->warningCount === 0
                && count($event->errors) === 1;
        });
    }

    #[Test]
    public function it_does_not_dispatch_grm_upload_processed_event_when_no_characters_are_processed(): void
    {
        Event::fake([GrmUploadProcessed::class]);

        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getCharacterStatus')
                ->andThrow(new \RuntimeException('Character not found'));
        });

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'FailChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ]);

        $job->handle(app(BlizzardService::class), $this->discord);

        Event::assertNotDispatched(GrmUploadProcessed::class);
    }

    #[Test]
    public function it_skips_empty_character_names(): void
    {
        $this->mockCharacterService([]);
        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => '', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
                ['Name' => '   ', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ]);

        $job->handle(app(BlizzardService::class), $this->discord);

        $this->assertDatabaseCount('characters', 0);
    }

    private function makeMessage(): MessageResource
    {
        return MessageResource::from([
            'id' => '9999999999999999999',
            'channel_id' => '1407688195386114119',
            'timestamp' => '2024-01-01T00:00:00.000000+00:00',
            'tts' => false,
            'mention_everyone' => false,
            'mention_roles' => [],
            'attachments' => [],
            'embeds' => [],
            'pinned' => false,
            'type' => MessageType::Default,
        ]);
    }

    /**
     * Mock the BlizzardService to return specific IDs for character names.
     *
     * @param  array<string, int>  $characterMap
     */
    protected function mockCharacterService(array $characterMap): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) use ($characterMap) {
            foreach ($characterMap as $name => $id) {
                $mock->shouldReceive('getCharacterStatus')
                    ->with($name)
                    ->andReturn(['id' => $id]);
                $mock->shouldReceive('getCharacterProfile')
                    ->with($name)
                    ->andReturn(['id' => $id, 'name' => $name, 'level' => 60]);
            }
        });
    }
}
