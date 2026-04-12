<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\Character;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\MediaService;
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
    private function makePlayableClassResponse(int $id = 7, string $name = 'Shaman'): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'gender_name' => ['male' => $name, 'female' => $name],
            'power_type' => [],
            'media' => [],
            'pvp_talent_slots' => [],
            'playable_races' => [],
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

    /**
     * @return array{id: int, assets: array<int, array{key: string, value: string, file_data_id: int}>}
     */
    private function makePlayableClassMediaResponse(int $id = 7): array
    {
        return [
            'id' => $id,
            'assets' => [
                [
                    'key' => 'icon',
                    'value' => "https://render.worldofwarcraft.com/eu/icons/56/class_{$id}.jpg",
                    'file_data_id' => 1000 + $id,
                ],
            ],
        ];
    }

    private function mockBlizzardAndMediaServices(?callable $blizzardCallback = null): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) use ($blizzardCallback) {
            $mock->shouldReceive('getCharacterProfile')
                ->andReturnUsing(fn () => $this->makeProfileResponse());
            $mock->shouldReceive('findPlayableClass')
                ->andReturnUsing(fn (int $id) => $this->makePlayableClassResponse($id));
            $mock->shouldReceive('findPlayableRace')
                ->andReturnUsing(fn (int $id) => $this->makePlayableRaceResponse($id));
            $mock->shouldReceive('getPlayableClassMedia')
                ->andReturnUsing(fn (int $id) => $this->makePlayableClassMediaResponse($id));

            if ($blizzardCallback) {
                $blizzardCallback($mock);
            }
        });

        $this->mock(MediaService::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')
                ->andReturn([1007 => 'https://cdn.local/class_7.jpg']);
        });
    }

    private function runSeeder(): void
    {
        Model::unguarded(fn () => app(CharacterSeeder::class)->run());
    }

    #[Test]
    public function seeder_populates_playable_class_and_playable_race_for_characters_missing_them(): void
    {
        $this->mockBlizzardAndMediaServices();

        $character = Character::factory()->create(['name' => 'Thrall']);

        $this->runSeeder();

        $fresh = $character->fresh();

        $this->assertSame(7, $fresh->playable_class['id']);
        $this->assertSame('Shaman', $fresh->playable_class['name']);
        $this->assertSame('https://cdn.local/class_7.jpg', $fresh->playable_class['icon_url']);
        $this->assertSame(2, $fresh->playable_race['id']);
        $this->assertSame('Orc', $fresh->playable_race['name']);
    }

    #[Test]
    public function seeder_skips_characters_with_both_columns_already_populated(): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('getCharacterProfile');
            $mock->shouldNotReceive('findPlayableClass');
            $mock->shouldNotReceive('findPlayableRace');
            $mock->shouldReceive('getPlayableClassMedia')
                ->andReturnUsing(fn (int $id) => $this->makePlayableClassMediaResponse($id));
        });

        $this->mock(MediaService::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')->andReturn([1001 => 'https://cdn.local/warrior.jpg']);
        });

        Character::factory()
            ->withPlayableClass(1, 'Warrior')
            ->withPlayableRace(1, 'Human')
            ->create(['name' => 'Thrall']);

        $this->runSeeder();
    }

    #[Test]
    public function seeder_processes_character_missing_only_playable_race(): void
    {
        $this->mockBlizzardAndMediaServices();

        $character = Character::factory()
            ->withPlayableClass(7, 'Shaman')
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

        $this->assertNull($fresh->getRawOriginal('playable_class'));
        $this->assertNull($fresh->getRawOriginal('playable_race'));
    }

    #[Test]
    public function seeder_persists_resolved_icon_url_from_media_service(): void
    {
        $this->mockBlizzardAndMediaServices();

        $character = Character::factory()->create(['name' => 'Thrall']);

        $this->runSeeder();

        $stored = $character->fresh()->getRawOriginal('playable_class');
        $decoded = json_decode($stored, true);

        $this->assertSame('https://cdn.local/class_7.jpg', $decoded['icon_url']);
    }
}
