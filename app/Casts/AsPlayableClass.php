<?php

namespace App\Casts;

use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\MediaService;
use App\Services\Blizzard\ValueObjects\PlayableClass;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<array{id: int|null, name: string, icon_url: string|null}, PlayableClass>
 */
class AsPlayableClass implements CastsAttributes
{
    /**
     * Cast the stored JSON into a plain {id, name, icon_url} array.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{id: int|null, name: string, icon_url: string|null}
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [
                'id' => null,
                'name' => 'Unknown Class',
                'icon_url' => app(MediaService::class)->get('inv_misc_questionmark'),
            ];
        }

        return is_string($value) ? json_decode($value, true) : $value;
    }

    /**
     * Accept a PlayableClass value object, resolve its icon URL via MediaService,
     * and serialize a reduced {id, name, icon_url} payload for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof PlayableClass) {
            throw new InvalidArgumentException(sprintf(
                'The %s attribute must be assigned a %s value object or null.',
                $key,
                PlayableClass::class,
            ));
        }

        $media = app(BlizzardService::class)->getPlayableClassMedia($value->id);
        $assets = Arr::get($media, 'assets', []);

        $iconUrl = null;

        if (! empty($assets)) {
            $urls = app(MediaService::class)->get($assets);
            $fileDataId = Arr::get($media, 'assets.0.file_data_id');
            $iconUrl = $fileDataId !== null ? Arr::get($urls, $fileDataId) : null;
        }

        return json_encode([
            'id' => $value->id,
            'name' => $value->name,
            'icon_url' => $iconUrl,
        ]);
    }
}
