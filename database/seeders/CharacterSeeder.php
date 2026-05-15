<?php

namespace Database\Seeders;

use App\Models\Character;
use App\Models\PlayableClass;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\ValueObjects\PlayableRaceData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
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
        $characters = Character::whereNull('playable_class_id')->orWhereNull('playable_race')->get();

        $characters->each(function (Character $character) {
            try {
                $profile = $this->blizzard->getCharacterProfile(Str::lower($character->name), config('services.blizzard.realm.slug'));

                $classId = Arr::get($profile, 'character_class.id');
                $playableClass = PlayableClass::find($classId);

                $character->update([
                    'playable_class_id' => $playableClass?->id,
                    'playable_race' => PlayableRaceData::from(
                        $this->blizzard->findPlayableRace(Arr::get($profile, 'race.id'))
                    ),
                ], ['touch' => false]);
            } catch (\Exception $e) {
                Log::warning("Failed to fetch profile for character {$character->name}. Skipping.", ['error' => $e->getMessage()]);

                return;
            }
        });
    }
}
