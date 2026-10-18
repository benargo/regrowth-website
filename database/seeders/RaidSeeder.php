<?php

namespace Database\Seeders;

use App\Models\Raid;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RaidSeeder extends Seeder
{
    /**
     * Seed the Burning Crusade raids, with their banners, into an empty raids table.
     */
    public function run(): void
    {
        if (Raid::exists()) {
            $this->command?->info('Raids already exist — skipping.');

            return;
        }

        $raids = [
            [
                'id' => 1,
                'name' => 'Karazhan',
                'difficulty' => 'Normal',
                'banner' => 'bg_karazhan_opera.webp',
                'color' => 0x8B7ED0, // 139, 126, 208
                'phase_id' => 1,
                'max_players' => 10,
                'max_loot_councillors' => 3,
            ],
            [
                'id' => 2,
                'name' => "Gruul's Lair",
                'difficulty' => 'Normal',
                'banner' => 'bg_gruul.webp',
                'color' => 0x9B443E, // 155, 68, 62
                'phase_id' => 1,
                'max_players' => 25,
                'max_loot_councillors' => 5,
            ],
            [
                'id' => 3,
                'name' => "Magtheridon's Lair",
                'difficulty' => 'Normal',
                'banner' => 'bg_magtheridon.webp',
                'color' => 0x2D7A5A, // 45, 122, 90
                'phase_id' => 1,
                'max_players' => 25,
                'max_loot_councillors' => 5,
            ],
            [
                'id' => 4,
                'name' => 'Serpentshrine Cavern',
                'difficulty' => 'Normal',
                'banner' => 'bg_serpentshrinecavern.webp',
                'banner_position' => 'center bottom',
                'color' => 0x226E73, // 34, 110, 115
                'phase_id' => 2,
                'max_players' => 25,
                'max_loot_councillors' => 5,
            ],
            [
                'id' => 5,
                'name' => 'Tempest Keep: The Eye',
                'difficulty' => 'Normal',
                'banner' => 'bg_tempestkeep.webp',
                'banner_position' => 'center bottom',
                'color' => 0xAE47EB, // 174, 71, 235
                'phase_id' => 2,
                'max_players' => 25,
                'max_loot_councillors' => 5,
            ],
            [
                'id' => 6,
                'name' => 'Hyjal Summit',
                'difficulty' => 'Normal',
                'banner' => 'bg_hyjalsummit.webp',
                'color' => 0x4D9E1F, // 77, 158, 31
                'phase_id' => 3,
                'max_players' => 25,
                'max_loot_councillors' => 5,
            ],
            [
                'id' => 7,
                'name' => 'Black Temple',
                'difficulty' => 'Normal',
                'banner' => 'bg_blacktemple.webp',
                'color' => 0x4AE832, // 74, 232, 50
                'phase_id' => 3,
                'max_players' => 25,
                'max_loot_councillors' => 5,
            ],
            [
                'id' => 8,
                'name' => "Zul'Aman",
                'difficulty' => 'Normal',
                'banner' => 'bg_zulaman.webp',
                'color' => 0x2AAFA0, // 42, 175, 160
                'phase_id' => 4,
                'max_players' => 10,
                'max_loot_councillors' => 3,
            ],
            [
                'id' => 9,
                'name' => 'Sunwell Plateau',
                'difficulty' => 'Normal',
                'banner' => 'bg_sunwell.webp',
                'color' => 0xC41F1F, // 196, 31, 31
                'phase_id' => 5,
                'max_players' => 25,
                'max_loot_councillors' => 5,
            ],
        ];

        DB::transaction(function () use ($raids): void {
            foreach ($raids as $row) {
                $raid = Raid::forceCreate(Arr::except($row, ['banner', 'banner_position']));

                $raid->addMedia(resource_path("images/{$row['banner']}"))
                    ->preservingOriginal()
                    ->withCustomProperties(isset($row['banner_position']) ? ['position' => $row['banner_position']] : [])
                    ->toMediaCollection(Raid::MEDIA_BANNER);
            }
        });
    }
}
