<?php

namespace Database\Seeders;

use App\Models\Character;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\ValueObjects\PlayableClass;
use App\Services\Blizzard\ValueObjects\PlayableRace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CharacterSeeder extends Seeder
{
    public function __construct(
        private BlizzardService $blizzard,
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Schema::hasColumns('characters', ['playable_class', 'playable_race'])) {
            $characters = Character::whereNull('playable_class')->orWhereNull('playable_race')->get();

            $characters->each(function (Character $character) {
                try {
                    $profile = $this->blizzard->getCharacterProfile(Str::lower($character->name), config('services.blizzard.realm.slug'));

                    $character->update([
                        'playable_class' => PlayableClass::fromApiResponse(
                            $this->blizzard->findPlayableClass(Arr::get($profile, 'character_class.id'))
                        ),
                        'playable_race' => PlayableRace::fromApiResponse(
                            $this->blizzard->findPlayableRace(Arr::get($profile, 'race.id'))
                        ),
                    ]);
                } catch (\Exception $e) {
                    Log::warning("Failed to fetch profile for character {$character->name}. Skipping.", ['error' => $e->getMessage()]);

                    return;
                }
            });
        }
    }
}
