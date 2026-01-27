<?php

namespace Database\Seeders\TBC;

use App\Models\TBC\Phase;
use Illuminate\Database\Seeder;

class PhaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $phases = [
            [
                'id' => 1,
                'description' => "Phase 1: Karazhan, Gruul's Lair, and Magtheridon's Lair",
                'start_date' => '2026-02-06 00:00:00',
            ],
            [
                'id' => 2,
                'description' => 'Phase 2: The Eye and Serpentshrine Cavern',
                'start_date' => null,
            ],
            [
                'id' => 3,
                'description' => 'Phase 3: Mount Hyjal and Black Temple',
                'start_date' => null,
            ],
            [
                'id' => 4,
                'description' => "Phase 4: Zul'Aman",
                'start_date' => null,
            ],
            [
                'id' => 5,
                'description' => 'Phase 5: Sunwell Plateau',
                'start_date' => null,
            ],
        ];

        foreach ($phases as $phase) {
            Phase::query()->updateOrCreate(
                ['id' => $phase['id']],
                $phase
            );
        }
    }
}
