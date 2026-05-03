<?php

namespace Database\Seeders;

use App\Models\Zone;
use App\Services\WarcraftLogs\ValueObjects\DifficultyData;
use App\Services\WarcraftLogs\ValueObjects\ExpansionData;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // These are the only two zones that have been used in reports so far, so we'll just seed those for now.
        $zones = [
            [
                'id' => 1047,
                'name' => 'Karazhan',
                'difficulties' => [new DifficultyData(
                    id: 3,
                    name: 'Normal',
                    sizes: [10]
                )],
            ],
            [
                'id' => 1048,
                'name' => 'Gruul / Magtheridon',
                'difficulties' => [new DifficultyData(
                    id: 3,
                    name: 'Normal',
                    sizes: [25]
                )],
            ],
        ];

        // We use updateOrCreate here so that if we need to update the name or difficulties of a zone in the future,
        // we can just update the seeder and re-run it without creating duplicate zones.
        foreach ($zones as $zone) {
            Zone::updateOrCreate(
                ['id' => $zone['id']],
                [
                    'name' => $zone['name'],
                    'difficulties' => $zone['difficulties'],
                    'expansion' => new ExpansionData(
                        id: 1001,
                        name: 'The Burning Crusade'
                    ),
                    'is_frozen' => false,
                ]
            );
        }
    }
}
