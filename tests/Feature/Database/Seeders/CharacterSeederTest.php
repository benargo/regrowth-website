<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\Character;
use App\Models\PlayableClass;
use App\Services\Blizzard\BlizzardService;
use Database\Seeders\CharacterSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function makeProfileResponse(int $classId = 7, int $raceId = 2): array
    {
        return [
            'character_class' => ['id' => $classId],
            'race' => ['id' => $raceId],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makePlayableRaceResponse(int $id = 2, string $name = 'Orc'): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'gender_name' => ['male' => $name, 'female' => $name],
            'faction' => ['type' => 'HORDE', 'name' => 'Horde'],
            'is_selectable' => true,
            'is_allied_race' => false,
            'playable_classes' => [],
            'racial_spells' => [],
        ];
    }

    private function mockBlizzardService(?callable $callback = null): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) use ($callback) {
            $mock->shouldReceive('getCharacterProfile')
                ->andReturnUsing(fn () => $this->makeProfileResponse());
            $mock->shouldReceive('findPlayableRace')
                ->andReturnUsing(fn (int $id) => $this->makePlayableRaceResponse($id));

            if ($callback) {
                $callback($mock);
            }
        });
    }

    private function runSeeder(): void
    {
        Model::unguarded(fn () => app(CharacterSeeder::class)->run());
    }

    #[Test]
    public function seeder_populates_playable_class_id_and_playable_race_for_characters_missing_them(): void
    {
        $playableClass = PlayableClass::factory()->create(['id' => 7, 'name' => 'Shaman']);

        $this->mockBlizzardService();

        $character = Character::factory()->create(['name' => 'Thrall']);

        $this->runSeeder();

        $fresh = $character->fresh();

        $this->assertSame(7, $fresh->playable_class_id);
        $this->assertTrue($fresh->playableClass->is($playableClass));
        $this->assertSame(2, $fresh->playable_race['id']);
        $this->assertSame('Orc', $fresh->playable_race['name']);
    }

    #[Test]
    public function seeder_skips_characters_with_both_columns_already_populated(): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('getCharacterProfile');
            $mock->shouldNotReceive('findPlayableRace');
        });

        Character::factory()
            ->withPlayableClass()
            ->withPlayableRace(1, 'Human')
            ->create(['name' => 'Thrall']);

        $this->runSeeder();
    }

    #[Test]
    public function seeder_processes_character_missing_only_playable_race(): void
    {
        PlayableClass::factory()->create(['id' => 7, 'name' => 'Shaman']);

        $this->mockBlizzardService();

        $character = Character::factory()
            ->withPlayableClass(PlayableClass::find(7))
            ->create(['name' => 'Thrall']);

        $this->runSeeder();

        $fresh = $character->fresh();

        $this->assertSame(2, $fresh->playable_race['id']);
        $this->assertSame('Orc', $fresh->playable_race['name']);
    }

    #[Test]
    public function seeder_logs_warning_and_continues_when_api_throws(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'Failed to fetch profile for character'));

        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getCharacterProfile')
                ->andThrow(new \RuntimeException('Blizzard API down'));
        });

        $character = Character::factory()->create(['name' => 'Thrall']);

        $this->runSeeder();

        $fresh = $character->fresh();

        $this->assertNull($fresh->playable_class_id);
        $this->assertNull($fresh->getRawOriginal('playable_race'));
    }

    #[Test]
    public function seeder_does_not_recurse_infinitely_when_characters_are_mutually_linked(): void
    {
        PlayableClass::factory()->create(['id' => 7, 'name' => 'Shaman']);

        $this->mockBlizzardService();

        $characterA = Character::factory()->create(['name' => 'Thrall']);
        $characterB = Character::factory()->create(['name' => 'Garrosh']);

        // Create a bidirectional link — this is what causes the recursive touch loop.
        \DB::table('character_links')->insert([
            ['character_id' => $characterA->id, 'linked_character_id' => $characterB->id, 'created_at' => now(), 'updated_at' => now()],
            ['character_id' => $characterB->id, 'linked_character_id' => $characterA->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->runSeeder();

        $this->assertSame(7, $characterA->fresh()->playable_class_id);
        $this->assertSame(7, $characterB->fresh()->playable_class_id);
    }

    #[Test]
    public function seeder_sets_null_playable_class_id_when_class_not_found_in_database(): void
    {
        $this->mockBlizzardService();

        $character = Character::factory()->create(['name' => 'Thrall']);

        $this->runSeeder();

        $this->assertNull($character->fresh()->playable_class_id);
    }
}
