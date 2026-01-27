<?php

namespace Database\Seeders\TBC;

use App\Models\TBC\Raid;
use Illuminate\Database\Seeder;

class RaidSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $raids = [
            [
                'id' => 1,
                'name' => 'Karazhan',
                'difficulty' => 'Normal',
                'phase_id' => 1,
                'max_players' => 10,
            ],
            [
                'id' => 2,
                'name' => "Gruul's Lair",
                'difficulty' => 'Normal',
                'phase_id' => 1,
                'max_players' => 25,
            ],
            [
                'id' => 3,
                'name' => "Magtheridon's Lair",
                'difficulty' => 'Normal',
                'phase_id' => 1,
                'max_players' => 25,
            ],
            [
                'id' => 4,
                'name' => 'Serpentshrine Cavern',
                'difficulty' => 'Normal',
                'phase_id' => 2,
                'max_players' => 25,
            ],
            [
                'id' => 5,
                'name' => 'Tempest Keep: The Eye',
                'difficulty' => 'Normal',
                'phase_id' => 2,
                'max_players' => 25,
            ],
            [
                'id' => 6,
                'name' => 'Hyjal Summit',
                'difficulty' => 'Normal',
                'phase_id' => 3,
                'max_players' => 25,
            ],
            [
                'id' => 7,
                'name' => 'Black Temple',
                'difficulty' => 'Normal',
                'phase_id' => 3,
                'max_players' => 25,
            ],
            [
                'id' => 8,
                'name' => "Zul'Aman",
                'difficulty' => 'Normal',
                'phase_id' => 4,
                'max_players' => 10,
            ],
            [
                'id' => 9,
                'name' => 'Sunwell Plateau',
                'difficulty' => 'Normal',
                'phase_id' => 5,
                'max_players' => 25,
            ],
        ];

        foreach ($raids as $raid) {
            Raid::query()->updateOrCreate(
                ['id' => $raid['id']],
                $raid
            );
        }
    }
}
