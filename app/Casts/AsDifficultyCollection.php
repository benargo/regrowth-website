<?php

namespace App\Casts;

use App\Services\WarcraftLogs\ValueObjects\DifficultyData;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class AsDifficultyCollection implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, DifficultyData>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Collection
    {
        $items = is_string($value) ? json_decode($value, associative: true) : (array) $value;

        return collect($items)->map(fn (array $item) => new DifficultyData(
            id: $item['id'],
            name: $item['name'],
            sizes: $item['sizes'] ?? [],
        ));
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  Collection<int, DifficultyData>|array<int, DifficultyData>  $value
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $items = $value instanceof Collection ? $value : collect($value);

        $items->each(function (mixed $item): void {
            if (! $item instanceof DifficultyData) {
                throw new InvalidArgumentException('Each item must be an instance of DifficultyData.');
            }
        });

        return json_encode($items->map->toArray()->values()->all());
    }
}
