<?php

namespace Database\Seeders\TBC;

use App\Models\TBC\Boss;
use Illuminate\Database\Seeder;

class BossSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bosses = [
            // Karazhan (raid_id: 1)
            ['id' => 1, 'name' => 'Attumen the Huntsman', 'raid_id' => 1, 'encounter_order' => 1],
            ['id' => 2, 'name' => 'Servants\' Quarters', 'raid_id' => 1, 'encounter_order' => 11],
            ['id' => 3, 'name' => 'Moroes', 'raid_id' => 1, 'encounter_order' => 2],
            ['id' => 4, 'name' => 'Maiden of Virtue', 'raid_id' => 1, 'encounter_order' => 3],
            ['id' => 5, 'name' => 'Opera Event', 'raid_id' => 1, 'encounter_order' => 4],
            ['id' => 6, 'name' => 'Nightbane', 'raid_id' => 1, 'encounter_order' => 5],
            ['id' => 7, 'name' => 'The Curator', 'raid_id' => 1, 'encounter_order' => 6],
            ['id' => 8, 'name' => 'Terestian Illhoof', 'raid_id' => 1, 'encounter_order' => 7],
            ['id' => 9, 'name' => 'Shade of Aran', 'raid_id' => 1, 'encounter_order' => 8],
            ['id' => 10, 'name' => 'Netherspite', 'raid_id' => 1, 'encounter_order' => 9],
            ['id' => 11, 'name' => 'Chess Event', 'raid_id' => 1, 'encounter_order' => 10],
            ['id' => 12, 'name' => 'Prince Malchezaar', 'raid_id' => 1, 'encounter_order' => 11],

            // Gruul's Lair (raid_id: 2)
            ['id' => 13, 'name' => 'High King Maulgar', 'raid_id' => 2, 'encounter_order' => 1],
            ['id' => 14, 'name' => 'Gruul the Dragonkiller', 'raid_id' => 2, 'encounter_order' => 2],

            // Magtheridon's Lair (raid_id: 3)
            ['id' => 15, 'name' => 'Magtheridon', 'raid_id' => 3, 'encounter_order' => 1],

            // Serpentshrine Cavern (raid_id: 4)
            ['id' => 16, 'name' => 'Hydross the Unstable', 'raid_id' => 4, 'encounter_order' => 1],
            ['id' => 17, 'name' => 'The Lurker Below', 'raid_id' => 4, 'encounter_order' => 2],
            ['id' => 18, 'name' => 'Leotheras the Blind', 'raid_id' => 4, 'encounter_order' => 3],
            ['id' => 19, 'name' => 'Fathom-Lord Karathress', 'raid_id' => 4, 'encounter_order' => 4],
            ['id' => 20, 'name' => 'Morogrim Tidewalker', 'raid_id' => 4, 'encounter_order' => 5],
            ['id' => 21, 'name' => 'Lady Vashj', 'raid_id' => 4, 'encounter_order' => 6],

            // The Eye (raid_id: 5)
            ['id' => 22, 'name' => "Al'ar", 'raid_id' => 5, 'encounter_order' => 1],
            ['id' => 23, 'name' => 'Void Reaver', 'raid_id' => 5, 'encounter_order' => 2],
            ['id' => 24, 'name' => 'High Astromancer Solarian', 'raid_id' => 5, 'encounter_order' => 3],
            ['id' => 25, 'name' => "Kael'thas Sunstrider", 'raid_id' => 5, 'encounter_order' => 4],

            // Mount Hyjal (raid_id: 6)
            ['id' => 26, 'name' => 'Rage Winterchill', 'raid_id' => 6, 'encounter_order' => 1],
            ['id' => 27, 'name' => 'Anetheron', 'raid_id' => 6, 'encounter_order' => 2],
            ['id' => 28, 'name' => "Kaz'rogal", 'raid_id' => 6, 'encounter_order' => 3],
            ['id' => 29, 'name' => 'Azgalor', 'raid_id' => 6, 'encounter_order' => 4],
            ['id' => 30, 'name' => 'Archimonde', 'raid_id' => 6, 'encounter_order' => 5],

            // Black Temple (raid_id: 7)
            ['id' => 31, 'name' => "High Warlord Naj'entus", 'raid_id' => 7, 'encounter_order' => 1],
            ['id' => 32, 'name' => 'Supremus', 'raid_id' => 7, 'encounter_order' => 2],
            ['id' => 33, 'name' => 'Shade of Akama', 'raid_id' => 7, 'encounter_order' => 3],
            ['id' => 34, 'name' => 'Teron Gorefiend', 'raid_id' => 7, 'encounter_order' => 4],
            ['id' => 35, 'name' => 'Reliquary of Souls', 'raid_id' => 7, 'encounter_order' => 5],
            ['id' => 36, 'name' => 'Gurtogg Bloodboil', 'raid_id' => 7, 'encounter_order' => 6],
            ['id' => 37, 'name' => 'Mother Shahraz', 'raid_id' => 7, 'encounter_order' => 7],
            ['id' => 38, 'name' => 'The Illidari Council', 'raid_id' => 7, 'encounter_order' => 8],
            ['id' => 39, 'name' => 'Illidan Stormrage', 'raid_id' => 7, 'encounter_order' => 9],

            // Zul'Aman (raid_id: 8)
            ['id' => 40, 'name' => "Akil'zon", 'raid_id' => 8, 'encounter_order' => 1],
            ['id' => 41, 'name' => 'Nalorakk', 'raid_id' => 8, 'encounter_order' => 2],
            ['id' => 42, 'name' => "Jan'alai", 'raid_id' => 8, 'encounter_order' => 3],
            ['id' => 43, 'name' => 'Halazzi', 'raid_id' => 8, 'encounter_order' => 4],
            ['id' => 44, 'name' => 'Hex Lord Malacrass', 'raid_id' => 8, 'encounter_order' => 5],
            ['id' => 45, 'name' => "Zul'jin", 'raid_id' => 8, 'encounter_order' => 6],

            // Sunwell Plateau (raid_id: 9)
            ['id' => 46, 'name' => 'Kalecgos', 'raid_id' => 9, 'encounter_order' => 1],
            ['id' => 47, 'name' => 'Brutallus', 'raid_id' => 9, 'encounter_order' => 2],
            ['id' => 48, 'name' => 'Felmyst', 'raid_id' => 9, 'encounter_order' => 3],
            ['id' => 49, 'name' => 'Eredar Twins', 'raid_id' => 9, 'encounter_order' => 4],
            ['id' => 50, 'name' => "M'uru", 'raid_id' => 9, 'encounter_order' => 5],
            ['id' => 51, 'name' => "Kil'jaeden", 'raid_id' => 9, 'encounter_order' => 6],
        ];

        foreach ($bosses as $boss) {
            Boss::query()->updateOrCreate(
                ['id' => $boss['id']],
                $boss
            );
        }
    }
}
