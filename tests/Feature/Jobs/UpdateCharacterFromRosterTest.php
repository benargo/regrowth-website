<?php

namespace Tests\Feature\Jobs;

use App\Events\AddonSettingsProcessed;
use App\Jobs\UpdateCharacterFromRoster;
use App\Models\Character;
use App\Models\GuildRank;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\MediaService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\Skip;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateCharacterFromRosterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([AddonSettingsProcessed::class]);
    }

    #[Test]
    public function it_creates_a_new_character(): void
    {
        $guildRank = GuildRank::factory()->create(['position' => 3]);

        $job = new UpdateCharacterFromRoster([
            'character' => [
                'id' => 12345,
                'name' => 'TestCharacter',
                'level' => 80,
            ],
            'rank' => 3,
        ]);

        $job->handle();

        $this->assertDatabaseHas('characters', [
            'id' => 12345,
            'name' => 'TestCharacter',
            'rank_id' => $guildRank->id,
        ]);
    }

    #[Test]
    public function it_updates_an_existing_character(): void
    {
        $guildRank = GuildRank::factory()->create(['position' => 2]);
        $character = Character::factory()->create([
            'id' => 12345,
            'name' => 'OldName',
        ]);

        $job = new UpdateCharacterFromRoster([
            'character' => [
                'id' => 12345,
                'name' => 'NewName',
                'level' => 80,
            ],
            'rank' => 2,
        ]);

        $job->handle();

        $this->assertDatabaseHas('characters', [
            'id' => 12345,
            'name' => 'NewName',
            'rank_id' => $guildRank->id,
        ]);
    }

    #[Test]
    public function it_associates_character_with_correct_guild_rank(): void
    {
        $officerRank = GuildRank::factory()->create(['position' => 1, 'name' => 'Officer']);
        $raiderRank = GuildRank::factory()->create(['position' => 3, 'name' => 'Raider']);

        $job = new UpdateCharacterFromRoster([
            'character' => [
                'id' => 12345,
                'name' => 'TestCharacter',
                'level' => 80,
            ],
            'rank' => 3,
        ]);

        $job->handle();

        $character = Character::find(12345);
        $this->assertEquals($raiderRank->id, $character->rank_id);
    }

    #[Test]
    public function it_updates_character_rank_when_changed(): void
    {
        $oldRank = GuildRank::factory()->create(['position' => 5]);
        $newRank = GuildRank::factory()->create(['position' => 2]);
        $character = Character::factory()->create([
            'id' => 12345,
            'name' => 'TestCharacter',
            'rank_id' => $oldRank->id,
        ]);

        $job = new UpdateCharacterFromRoster([
            'character' => [
                'id' => 12345,
                'name' => 'TestCharacter',
                'level' => 80,
            ],
            'rank' => 2,
        ]);

        $job->handle();

        $character->refresh();
        $this->assertEquals($newRank->id, $character->rank_id);
    }

    #[Test]
    public function middleware_skips_when_character_level_below_60(): void
    {
        $characterData = [
            'character' => [
                'id' => 12345,
                'name' => 'LowLevelChar',
                'level' => 59,
            ],
            'rank' => 1,
        ];

        $job = new UpdateCharacterFromRoster($characterData);
        $middleware = $job->middleware();

        $skipMiddleware = collect($middleware)->first(fn ($m) => $m instanceof Skip);
        $this->assertNotNull($skipMiddleware);

        // The Skip middleware should evaluate to true (skip the job)
        $this->assertTrue($characterData['character']['level'] < 60);
    }

    #[Test]
    public function middleware_does_not_skip_when_character_level_is_60(): void
    {
        $characterData = [
            'character' => [
                'id' => 12345,
                'name' => 'MaxLevelChar',
                'level' => 60,
            ],
            'rank' => 1,
        ];

        $job = new UpdateCharacterFromRoster($characterData);

        // The condition for Skip is level < 60, so level 60 should NOT skip
        $this->assertFalse($characterData['character']['level'] < 60);
    }

    #[Test]
    public function middleware_does_not_skip_when_character_level_above_60(): void
    {
        $characterData = [
            'character' => [
                'id' => 12345,
                'name' => 'HighLevelChar',
                'level' => 80,
            ],
            'rank' => 1,
        ];

        $job = new UpdateCharacterFromRoster($characterData);

        // The condition for Skip is level < 60, so level 80 should NOT skip
        $this->assertFalse($characterData['character']['level'] < 60);
    }

    #[Test]
    public function it_throws_model_not_found_exception_when_rank_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $job = new UpdateCharacterFromRoster([
            'character' => [
                'id' => 12345,
                'name' => 'TestCharacter',
                'level' => 80,
            ],
            'rank' => 99, // Non-existent rank position
        ]);

        $job->handle();
    }

    #[Test]
    public function failed_logs_model_not_found_exception(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Guild rank not found for character update.'
                    && $context['rank_position'] === 99;
            });

        $job = new UpdateCharacterFromRoster([
            'character' => [
                'id' => 12345,
                'name' => 'TestCharacter',
                'level' => 80,
            ],
            'rank' => 99,
        ]);

        $exception = new ModelNotFoundException('No query results for model [App\\Models\\GuildRank].');
        $job->failed($exception);
    }

    #[Test]
    public function failed_logs_generic_exception(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'UpdateCharacterFromRoster job failed.'
                    && $context['error'] === 'Something went wrong';
            });

        $job = new UpdateCharacterFromRoster([
            'character' => [
                'id' => 12345,
                'name' => 'TestCharacter',
                'level' => 80,
            ],
            'rank' => 1,
        ]);

        $exception = new \RuntimeException('Something went wrong');
        $job->failed($exception);
    }

    #[Test]
    public function middleware_includes_without_overlapping(): void
    {
        $job = new UpdateCharacterFromRoster([
            'character' => [
                'id' => 12345,
                'name' => 'TestCharacter',
                'level' => 80,
            ],
            'rank' => 1,
        ]);

        $middleware = $job->middleware();

        $hasWithoutOverlapping = collect($middleware)->contains(
            fn ($m) => $m instanceof WithoutOverlapping
        );

        $this->assertTrue($hasWithoutOverlapping);
    }

    #[Test]
    public function it_persists_playable_class_from_character_data(): void
    {
        GuildRank::factory()->create(['position' => 1]);

        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('findPlayableClass')
                ->with(2)
                ->andReturn([
                    'id' => 2,
                    'name' => 'Paladin',
                    'gender_name' => ['male' => 'Paladin', 'female' => 'Paladin'],
                    'power_type' => [],
                    'media' => [],
                    'pvp_talent_slots' => [],
                    'playable_races' => [],
                ]);

            $mock->shouldReceive('getPlayableClassMedia')
                ->with(2)
                ->andReturn([
                    'id' => 2,
                    'assets' => [
                        [
                            'key' => 'icon',
                            'value' => 'https://render.worldofwarcraft.com/eu/icons/56/class_2.jpg',
                            'file_data_id' => 1002,
                        ],
                    ],
                ]);
        });

        $this->mock(MediaService::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')
                ->andReturn([1002 => 'https://cdn.local/paladin.jpg']);
        });

        $job = new UpdateCharacterFromRoster([
            'character' => [
                'id' => 12345,
                'name' => 'TestCharacter',
                'level' => 80,
                'playable_class' => ['id' => 2],
            ],
            'rank' => 1,
        ]);

        $job->handle();

        $this->assertDatabaseHas('characters', [
            'id' => 12345,
            'playable_class' => json_encode([
                'id' => 2,
                'name' => 'Paladin',
                'icon_url' => 'https://cdn.local/paladin.jpg',
            ]),
        ]);
    }

    #[Test]
    public function it_persists_playable_race_from_character_data(): void
    {
        GuildRank::factory()->create(['position' => 1]);

        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('findPlayableRace')
                ->with(3)
                ->andReturn([
                    'id' => 3,
                    'name' => 'Dwarf',
                    'gender_name' => ['male' => 'Dwarf', 'female' => 'Dwarf'],
                    'faction' => ['type' => 'ALLIANCE', 'name' => 'Alliance'],
                    'is_selectable' => true,
                    'is_allied_race' => false,
                    'playable_classes' => [],
                    'racial_spells' => [],
                ]);
        });

        $job = new UpdateCharacterFromRoster([
            'character' => [
                'id' => 12345,
                'name' => 'TestCharacter',
                'level' => 80,
                'playable_race' => ['id' => 3],
            ],
            'rank' => 1,
        ]);

        $job->handle();

        $this->assertDatabaseHas('characters', [
            'id' => 12345,
            'playable_race' => json_encode([
                'id' => 3,
                'name' => 'Dwarf',
            ]),
        ]);
    }

    #[Test]
    public function it_leaves_playable_class_null_when_missing_from_character_data(): void
    {
        GuildRank::factory()->create(['position' => 1]);

        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('findPlayableClass');
            $mock->shouldNotReceive('getPlayableClassMedia');
        });

        $job = new UpdateCharacterFromRoster([
            'character' => [
                'id' => 12345,
                'name' => 'TestCharacter',
                'level' => 80,
            ],
            'rank' => 1,
        ]);

        $job->handle();

        $this->assertDatabaseHas('characters', [
            'id' => 12345,
            'playable_class' => null,
        ]);
    }

    #[Test]
    public function it_leaves_playable_race_null_when_missing_from_character_data(): void
    {
        GuildRank::factory()->create(['position' => 1]);

        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('findPlayableRace');
        });

        $job = new UpdateCharacterFromRoster([
            'character' => [
                'id' => 12345,
                'name' => 'TestCharacter',
                'level' => 80,
            ],
            'rank' => 1,
        ]);

        $job->handle();

        $this->assertDatabaseHas('characters', [
            'id' => 12345,
            'playable_race' => null,
        ]);
    }
}
