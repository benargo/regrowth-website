<?php

namespace Tests\Feature\Database\Seeders;

use App\Enums\Gender;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Models\Character;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use Database\Seeders\CharacterSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

#[Group('characters')]
class CharacterSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function makeProfileResponse(int $classId = 7, int $raceId = 2, string $gender = 'Male'): array
    {
        return [
            'id' => 1,
            'name' => 'Thrall',
            'gender' => ['type' => strtoupper($gender), 'name' => $gender],
            'faction' => ['type' => 'HORDE', 'name' => 'Horde'],
            'race' => ['key' => ['href' => "https://example.test/race/{$raceId}"], 'name' => 'Orc', 'id' => $raceId],
            'character_class' => ['key' => ['href' => "https://example.test/class/{$classId}"], 'name' => 'Shaman', 'id' => $classId],
            'realm' => ['key' => ['href' => 'https://example.test/realm/1'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike'],
            'level' => 70,
            'last_login_timestamp' => 0,
            'average_item_level' => 0,
            'equipped_item_level' => 0,
        ];
    }

    private function fakeSaloon(int $classId = 7, int $raceId = 2): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(
                body: ['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600],
                status: 200,
            ),
            GetCharacterProfileRequest::class => MockResponse::make(
                body: $this->makeProfileResponse($classId, $raceId),
                status: 200,
            ),
        ]);
    }

    private function runSeeder(): void
    {
        Model::unguarded(fn () => app(CharacterSeeder::class)->run());
    }

    #[Test]
    public function seeder_populates_playable_class_id_and_playable_race_for_characters_missing_them(): void
    {
        $playableClass = PlayableClass::factory()->create(['id' => 7, 'name' => 'Shaman']);
        PlayableRace::factory()->create(['id' => 2, 'name' => 'Orc']);

        $this->fakeSaloon();

        $character = Character::factory()->create(['name' => 'Thrall']);

        $this->runSeeder();

        $fresh = $character->fresh();

        $this->assertSame(7, $fresh->playable_class_id);
        $this->assertTrue($fresh->playableClass->is($playableClass));
        $this->assertSame(2, $fresh->playable_race_id);
        $this->assertSame('Orc', $fresh->playableRace->name);
    }

    #[Test]
    public function seeder_skips_characters_with_both_columns_already_populated(): void
    {
        Saloon::fake([]);

        Character::factory()
            ->withPlayableClass()
            ->withPlayableRace(PlayableRace::factory()->create(['id' => 1, 'name' => 'Human']))
            ->create(['name' => 'Thrall', 'gender' => Gender::MALE]);

        $this->runSeeder();

        Saloon::assertNothingSent();
    }

    #[Test]
    public function seeder_processes_character_missing_only_playable_race(): void
    {
        PlayableClass::factory()->create(['id' => 7, 'name' => 'Shaman']);
        PlayableRace::factory()->create(['id' => 2, 'name' => 'Orc']);

        $this->fakeSaloon();

        $character = Character::factory()
            ->withPlayableClass(PlayableClass::find(7))
            ->create(['name' => 'Thrall']);

        $this->runSeeder();

        $fresh = $character->fresh();

        $this->assertSame(2, $fresh->playable_race_id);
        $this->assertSame('Orc', $fresh->playableRace->name);
    }

    #[Test]
    public function seeder_logs_warning_and_continues_when_api_throws(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'Failed to fetch profile for character'));

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(
                body: ['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600],
                status: 200,
            ),
            GetCharacterProfileRequest::class => MockResponse::make(
                body: ['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'],
                status: 404,
            ),
        ]);

        $character = Character::factory()->create(['name' => 'Thrall']);

        $this->runSeeder();

        $fresh = $character->fresh();

        $this->assertNull($fresh->playable_class_id);
        $this->assertNull($fresh->playable_race_id);
    }

    #[Test]
    public function seeder_does_not_recurse_infinitely_when_characters_are_mutually_linked(): void
    {
        PlayableClass::factory()->create(['id' => 7, 'name' => 'Shaman']);
        PlayableRace::factory()->create(['id' => 2, 'name' => 'Orc']);

        $this->fakeSaloon();

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
        PlayableRace::factory()->create(['id' => 2, 'name' => 'Orc']);

        $this->fakeSaloon();

        $character = Character::factory()->create(['name' => 'Thrall']);

        $this->runSeeder();

        $this->assertNull($character->fresh()->playable_class_id);
    }

    #[Test]
    public function seeder_populates_gender_for_characters_missing_it(): void
    {
        $playableClass = PlayableClass::factory()->create(['id' => 7, 'name' => 'Shaman']);
        PlayableRace::factory()->create(['id' => 2, 'name' => 'Orc']);

        $this->fakeSaloon();

        $character = Character::factory()
            ->withPlayableClass($playableClass)
            ->withPlayableRace(PlayableRace::find(2))
            ->create(['name' => 'Thrall', 'gender' => null]);

        $this->runSeeder();

        $this->assertSame(Gender::MALE, $character->fresh()->gender);
    }

    #[Test]
    public function seeder_populates_female_gender_from_api_response(): void
    {
        $playableClass = PlayableClass::factory()->create(['id' => 7, 'name' => 'Shaman']);
        PlayableRace::factory()->create(['id' => 2, 'name' => 'Orc']);

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(
                body: ['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600],
                status: 200,
            ),
            GetCharacterProfileRequest::class => MockResponse::make(
                body: $this->makeProfileResponse(gender: 'Female'),
                status: 200,
            ),
        ]);

        $character = Character::factory()
            ->withPlayableClass($playableClass)
            ->withPlayableRace(PlayableRace::find(2))
            ->create(['name' => 'Thrall', 'gender' => null]);

        $this->runSeeder();

        $this->assertSame(Gender::FEMALE, $character->fresh()->gender);
    }
}
