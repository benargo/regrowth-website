<?php

namespace Database\Seeders;

use App\Models\PlayableClass;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\MediaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class PlayableClassSeeder extends Seeder
{
    /**
     * Inject the BlizzardService and MediaService to fetch class data and media from the Blizzard API.
     */
    public function __construct(
        private BlizzardService $blizzardService,
        private MediaService $mediaService,
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classes = Arr::get($this->blizzardService->getPlayableClasses(), 'classes', []);

        foreach ($classes as $class) {
            $model = PlayableClass::updateOrCreate(
                ['id' => Arr::get($class, 'id')],
                ['name' => Arr::get($class, 'name')]
            );

            $assets = Arr::get(
                $this->blizzardService->getPlayableClassMedia(Arr::get($class, 'id')),
                'assets',
                []
            );

            $model->clearMediaCollection('blizzard_icons');

            foreach ($assets as $asset) {
                if (Arr::has($asset, 'value', [])) {
                    $model->addMediaFromUrl(Arr::get($asset, 'value'))->toMediaCollection('blizzard_icons');
                }
            }
        }
    }
}
