<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessGrmUpload;
use App\Models\Character;
use App\Models\GuildRank;
use App\Notifications\DiscordNotifiable;
use App\Notifications\GrmUploadFailed;
use App\Services\Blizzard\CharacterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use Tests\TestCase;

class ProcessGrmUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        config([
            'services.discord.channels.officer' => '1407688195386114119',
        ]);
    }

    public function test_it_creates_character_from_csv_row(): void
    {
        $this->mockCharacterService(['TestChar' => 12345]);

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ]);

        $job->handle(app(CharacterService::class));

        $this->assertDatabaseHas('characters', [
            'id' => 12345,
            'name' => 'TestChar',
            'is_main' => true,
        ]);
    }

    public function test_it_associates_character_with_rank(): void
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

        $job->handle(app(CharacterService::class));

        $character = Character::find(12345);
        $this->assertEquals($rank->id, $character->rank_id);
    }

    public function test_it_sets_is_main_false_for_alt_characters(): void
    {
        $this->mockCharacterService(['AltChar' => 67890]);
        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'AltChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Alt', 'Player Alts' => ''],
            ],
        ]);

        $job->handle(app(CharacterService::class));

        $this->assertDatabaseHas('characters', [
            'id' => 67890,
            'is_main' => false,
        ]);
    }

    public function test_it_creates_character_links_for_main_with_alts(): void
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

        $job->handle(app(CharacterService::class));

        $this->assertDatabaseHas('character_links', [
            'character_id' => 11111,
            'linked_character_id' => 22222,
        ]);

        $this->assertDatabaseHas('character_links', [
            'character_id' => 11111,
            'linked_character_id' => 33333,
        ]);
    }

    public function test_it_strips_realm_suffix_from_alt_names(): void
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

        $job->handle(app(CharacterService::class));

        $this->assertDatabaseHas('characters', [
            'id' => 22222,
            'name' => 'AltChar',
        ]);
    }

    public function test_it_strips_realm_suffix_with_spaces(): void
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

        $job->handle(app(CharacterService::class));

        $this->assertDatabaseHas('characters', [
            'id' => 22222,
            'name' => 'AltChar',
        ]);
    }

    public function test_it_uses_opposite_delimiter_for_alt_list(): void
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

        $job->handle(app(CharacterService::class));

        $this->assertDatabaseCount('character_links', 2);
    }

    public function test_it_continues_processing_on_individual_row_error(): void
    {
        $this->mock(CharacterService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getStatus')
                ->with('FailChar')
                ->andThrow(new \RuntimeException('Character not found'));

            $mock->shouldReceive('getStatus')
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

        $job->handle(app(CharacterService::class));

        $this->assertDatabaseHas('characters', ['id' => 99999]);
        $this->assertDatabaseMissing('characters', ['name' => 'FailChar']);
    }

    public function test_it_sends_discord_notification_on_errors(): void
    {
        $this->mock(CharacterService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getStatus')
                ->andThrow(new \RuntimeException('Character not found'));
        });

        $job = new ProcessGrmUpload([
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'FailChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ]);

        $job->handle(app(CharacterService::class));

        Notification::assertSentTo(
            new DiscordNotifiable('officer'),
            GrmUploadFailed::class
        );
    }

    public function test_it_does_not_create_duplicate_character_links(): void
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

        $job->handle(app(CharacterService::class));

        // Should still only have one link
        $this->assertDatabaseCount('character_links', 1);
    }

    public function test_it_updates_existing_character_data(): void
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

        $job->handle(app(CharacterService::class));

        // Should be updated to main
        $this->assertDatabaseHas('characters', [
            'id' => 12345,
            'is_main' => true,
        ]);
    }

    public function test_it_skips_empty_character_names(): void
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

        $job->handle(app(CharacterService::class));

        $this->assertDatabaseCount('characters', 0);
    }

    /**
     * Mock the CharacterService to return specific IDs for character names.
     *
     * @param  array<string, int>  $characterMap
     */
    protected function mockCharacterService(array $characterMap): void
    {
        $this->mock(CharacterService::class, function (MockInterface $mock) use ($characterMap) {
            foreach ($characterMap as $name => $id) {
                $mock->shouldReceive('getStatus')
                    ->with($name)
                    ->andReturn(['id' => $id]);
                $mock->shouldReceive('getProfile')
                    ->with($name)
                    ->andReturn(['id' => $id, 'name' => $name, 'level' => 60]);
            }
        });
    }
}
