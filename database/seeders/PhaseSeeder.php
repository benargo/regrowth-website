<?php

namespace Database\Seeders;

use App\Models\Phase;
use Illuminate\Database\Seeder;

class PhaseSeeder extends Seeder
{
    /**
     * Seed the Burning Crusade phases into an empty phases table.
     */
    public function run(): void
    {
        if (Phase::exists()) {
            $this->command?->info('Phases already exist — skipping.');

            return;
        }

        $phases = [
            [
                'id' => 1,
                'number' => 1,
                'description' => "Phase 1: Karazhan, Gruul's Lair, and Magtheridon's Lair",
                'start_date' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'number' => 2,
                'description' => 'Phase 2: The Eye and Serpentshrine Cavern',
                'start_date' => '2026-05-15 00:00:00',
            ],
            [
                'id' => 3,
                'number' => 3,
                'description' => 'Phase 3: Mount Hyjal and Black Temple',
                'start_date' => '2026-08-28 00:00:00',
            ],
            [
                'id' => 4,
                'number' => 3.5,
                'description' => "Phase 3.5: Zul'Aman",
                'start_date' => '2026-10-23 00:00:00',
            ],
            [
                'id' => 5,
                'number' => 4,
                'description' => 'Phase 4: Sunwell Plateau',
                'start_date' => null,
            ],
        ];

        foreach ($phases as $phase) {
            Phase::forceCreate($phase);
        }
    }
}
