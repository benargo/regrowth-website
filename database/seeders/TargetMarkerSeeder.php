<?php

namespace Database\Seeders;

use App\Models\TargetMarker;
use Illuminate\Database\Seeder;

class TargetMarkerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $icons = [
            ['slug' => 'skull', 'name' => 'Skull'],
            ['slug' => 'cross', 'name' => 'Cross'],
            ['slug' => 'square', 'name' => 'Square'],
            ['slug' => 'moon', 'name' => 'Moon'],
            ['slug' => 'triangle', 'name' => 'Triangle'],
            ['slug' => 'diamond', 'name' => 'Diamond'],
            ['slug' => 'circle', 'name' => 'Circle'],
            ['slug' => 'star', 'name' => 'Star'],
        ];

        foreach ($icons as $icon) {
            TargetMarker::updateOrCreate(
                ['slug' => $icon['slug']],
                ['name' => $icon['name']]
            );
        }
    }
}
