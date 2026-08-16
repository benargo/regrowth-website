<?php

namespace Database\Seeders;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use App\Http\Integrations\Blizzard\Exceptions\MediaNotFoundException;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassIndexRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use App\Jobs\AttachBlizzardIconToModel;
use App\Models\PlayableClass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\ForbiddenException;

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

            $existingFileNames = $model->getMedia('blizzard_icons')->pluck('file_name')->all();

            foreach ($mediaDto->assets as $asset) {
                $fileName = (string) Str::of($asset->value)->afterLast('/')->before('?');

                if (in_array($fileName, $existingFileNames, true)) {
                    continue;
                }

                try {
                    $body = $this->renderConnector->send(new FetchIconRequest($asset->value))->body();

                    $model->addMediaFromString($body)
                        ->usingFileName($fileName)
                        ->withCustomProperties(['size' => 56])
                        ->toMediaCollection('blizzard_icons');
                } catch (ForbiddenException $e) {
                    AttachBlizzardIconToModel::dispatch(PlayableClass::class, $model->id, $asset->value)
                        ->delay(now()->addMinutes(5));
                } catch (MediaNotFoundException|RequestException $e) {
                    report($e);
                }
            }
        }
    }
}
