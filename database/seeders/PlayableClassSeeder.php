<?php

namespace Database\Seeders;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassIndexRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use App\Models\PlayableClass;
use Illuminate\Database\Seeder;

class PlayableClassSeeder extends Seeder
{
    public function __construct(
        private BlizzardConnector $blizzard,
        private RenderConnector $renderConnector,
    ) {}

    public function run(): void
    {
        /** @var array<int, LinkData> $classes */
        $classes = $this->blizzard->send(new GetPlayableClassIndexRequest)->dto();

        foreach ($classes as $class) {
            $model = PlayableClass::updateOrCreate(
                ['id' => $class->id],
                ['name' => $class->name],
            );

            $mediaDto = $this->blizzard->send(new GetPlayableClassMediaRequest($class->id))->dto();

            $model->clearMediaCollection('blizzard_icons');

            foreach ($mediaDto->assets as $asset) {
                $body = $this->renderConnector->send(new FetchAssetRequest($asset->value))->body();

                $model->addMediaFromString($body)->toMediaCollection('blizzard_icons');
            }
        }
    }
}
