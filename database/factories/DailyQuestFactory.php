<?php

namespace Database\Factories;

use App\Enums\DailyQuestType;
use App\Enums\Instance;
use App\Models\DailyQuest;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

#[UseModel(DailyQuest::class)]
class DailyQuestFactory extends Factory
{
    protected $model = DailyQuest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(DailyQuestType::cases()),
            'instance' => null,
        ];
    }

    public function cooking(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DailyQuestType::Cooking,
            'instance' => null,
        ]);
    }

    public function fishing(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DailyQuestType::Fishing,
            'instance' => null,
        ]);
    }

    public function dungeon(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DailyQuestType::Dungeon,
            'instance' => fake()->randomElement([
                Instance::Arcatraz,
                Instance::Steamvault,
                Instance::ShadowLabyrinth,
                Instance::BlackMorass,
                Instance::ShatteredHalls,
                Instance::Botanica,
                Instance::Mechanar,
            ]),
        ]);
    }

    public function instance(): static
    {
        return $this->dungeon();
    }

    public function heroic(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DailyQuestType::Heroic,
            'instance' => fake()->randomElement([
                Instance::Underbog,
                Instance::Botanica,
                Instance::BlackMorass,
                Instance::ShatteredHalls,
                Instance::BloodFurnace,
                Instance::ShadowLabyrinth,
                Instance::HellfireRamparts,
                Instance::Mechanar,
                Instance::ManaTombs,
                Instance::OldHillsbradFoothills,
                Instance::AuchenaiCrypts,
                Instance::SethekkHalls,
                Instance::SlavePens,
                Instance::Arcatraz,
                Instance::Steamvault,
            ]),
        ]);
    }

    public function pvp(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DailyQuestType::PvP,
            'instance' => fake()->randomElement([
                Instance::AlteracValley,
                Instance::ArathiBasin,
                Instance::EyeOfTheStorm,
                Instance::WarsongGulch,
            ]),
        ]);
    }
}
