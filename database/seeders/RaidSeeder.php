<?php

namespace Database\Seeders;

use App\Enums\RaidBackground;
use App\Models\Raid;
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
                'background_css_class' => RaidBackground::KARAZHAN,
                'color' => 0x8B7ED0,
                'phase_id' => 1,
                'max_players' => 10,
                'max_loot_councillors' => 3,
            ],
            [
                'id' => 2,
                'name' => "Gruul's Lair",
                'difficulty' => 'Normal',
                'background_css_class' => RaidBackground::GRUUL_MAGTHERIDON,
                'color' => 0x9B443E,
                'phase_id' => 1,
                'max_players' => 25,
                'max_loot_councillors' => 5,
            ],
            [
                'id' => 3,
                'name' => "Magtheridon's Lair",
                'difficulty' => 'Normal',
                'background_css_class' => RaidBackground::GRUUL_MAGTHERIDON,
                'color' => 0x2D7A5A,
                'phase_id' => 1,
                'max_players' => 25,
                'max_loot_councillors' => 5,
            ],
            [
                'id' => 4,
                'name' => 'Serpentshrine Cavern',
                'difficulty' => 'Normal',
                'background_css_class' => RaidBackground::SERPENTSHRINE_CAVERN,
                'color' => 0x226E73,
                'phase_id' => 2,
                'max_players' => 25,
                'max_loot_councillors' => 5,
            ],
            [
                'id' => 5,
                'name' => 'Tempest Keep: The Eye',
                'difficulty' => 'Normal',
                'background_css_class' => RaidBackground::TEMPEST_KEEP,
                'color' => 0xAE47EB,
                'phase_id' => 2,
                'max_players' => 25,
                'max_loot_councillors' => 5,
            ],
            [
                'id' => 6,
                'name' => 'Hyjal Summit',
                'difficulty' => 'Normal',
                'color' => 0x5A9BD5,
                'phase_id' => 3,
                'max_players' => 25,
                'max_loot_councillors' => 5,
            ],
            [
                'id' => 7,
                'name' => 'Black Temple',
                'difficulty' => 'Normal',
                'phase_id' => 3,
                'max_players' => 25,
                'max_loot_councillors' => 5,
            ],
            [
                'id' => 8,
                'name' => "Zul'Aman",
                'difficulty' => 'Normal',
                'phase_id' => 4,
                'max_players' => 10,
                'max_loot_councillors' => 3,
            ],
            [
                'id' => 9,
                'name' => 'Sunwell Plateau',
                'difficulty' => 'Normal',
                'phase_id' => 5,
                'max_players' => 25,
                'max_loot_councillors' => 5,
            ],
        ];

        foreach ($raids as $raid) {
            Raid::updateOrCreate(
                ['id' => $raid['id']],
                $raid
            );
        }
    }
}
