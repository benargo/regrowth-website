<?php

namespace Database\Factories;

use App\Models\PlayableRace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayableRace>
 */
class PlayableRaceFactory extends Factory
{
    private array $raceNames = [
        'Blood Elf',
        'Dark Iron Dwarf',
        'Draenei',
        'Dwarf',
        'Gnome',
        'Goblin',
        'Highmountain Tauren',
        'Human',
        'Kul Tiran',
        'Lightforged Draenei',
        "Mag'har Orc",
        'Mechagnome',
        'Night Elf',
        'Nightborne',
        'Orc',
        'Pandaren',
        'Tauren',
        'Troll',
        'Undead',
        'Void Elf',
        'Vulpera',
        'Worgen',
        'Zandalari Troll',
        'Ashborne',
        'Crystalkin',
        'Deepstrider',
        'Embersoul',
        'Frostwalker',
        'Gilded Gnome',
        'Grimfang',
        'Hollowed',
        'Ironscale',
        'Jadeclaw',
        'Kindleborn',
        'Lunarkin',
        'Mirefolk',
        'Netherspawn',
        'Obsidian Dwarf',
        'Plagueborn',
        'Quicksilver',
        'Rimeborn',
        'Sandwalker',
        'Shadowmeld Elf',
        'Stonehide',
        'Thornback',
        'Umbral Troll',
        'Verdant',
        'Waveborn',
        'Wyrmkin',
        'Ashenveil',
        'Bonecaller',
        'Copperclaw',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->unique()->numberBetween(1, 10000),
            'name' => $this->faker->unique()->randomElement($this->raceNames),
        ];
    }
}
