<?php

namespace App\Casts;

use App\Services\Blizzard\ValueObjects\PlayableRace;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<array{id: int|null, name: string}, PlayableRace>
 */
class AsPlayableRace implements CastsAttributes
{
    /**
     * Cast the stored JSON into a plain {id, name} array.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{id: int|null, name: string}
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [
                'id' => null,
                'name' => 'Unknown Race',
            ];
        }

        return is_string($value) ? json_decode($value, true) : $value;
    }

    /**
     * Accept a PlayableRace value object and serialize a reduced {id, name}
     * payload for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof PlayableRace) {
            throw new InvalidArgumentException(sprintf(
                'The %s attribute must be assigned a %s value object or null.',
                $key,
                PlayableRace::class,
            ));
        }

        return json_encode([
            'id' => $value->id,
            'name' => $value->name,
        ]);
    }
}
