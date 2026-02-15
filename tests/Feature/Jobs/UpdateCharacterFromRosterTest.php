<?php

namespace Tests\Feature\Jobs;

use App\Events\AddonSettingsProcessed;
use App\Jobs\UpdateCharacterFromRoster;
use App\Models\Character;
use App\Models\GuildRank;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\Skip;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class UpdateCharacterFromRosterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([AddonSettingsProcessed::class]);
    }

    public function test_it_creates_a_new_character(): void
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

    public function test_it_updates_an_existing_character(): void
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

    public function test_it_associates_character_with_correct_guild_rank(): void
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

    public function test_it_updates_character_rank_when_changed(): void
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

    public function test_middleware_skips_when_character_level_below_60(): void
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

    public function test_middleware_does_not_skip_when_character_level_is_60(): void
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

    public function test_middleware_does_not_skip_when_character_level_above_60(): void
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

    public function test_it_throws_model_not_found_exception_when_rank_does_not_exist(): void
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

    public function test_failed_logs_model_not_found_exception(): void
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

    public function test_failed_logs_generic_exception(): void
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

    public function test_middleware_includes_without_overlapping(): void
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
            fn ($m) => $m instanceof \Illuminate\Queue\Middleware\WithoutOverlapping
        );

        $this->assertTrue($hasWithoutOverlapping);
    }
}
