<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class AsRelationshipIndex implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     * @return Collection<string, Model>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Collection
    {
        $items = is_string($value) ? json_decode($value, associative: true) : (array) $value;

        return collect($items)
            ->filter()
            ->mapWithKeys(function (array $entry): array {
                $instance = app($entry['model'])->find($entry['key']);

                return [$entry['name'] => $instance];
            });
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  Collection<string, mixed>|array<int, array{name: string, model: string, key: mixed}>  $value
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (is_string($value)) {
            $value = json_decode($value, associative: true);
        }

        $items = $value instanceof Collection ? $value->values() : collect($value);

        $items->each(function (mixed $entry): void {
            $validator = Validator::make(
                (array) $entry,
                ['name' => 'required|string', 'model' => 'required|string', 'key' => 'required'],
            );

            if ($validator->fails()) {
                throw new InvalidArgumentException(
                    'Invalid relationship entry: '.implode(', ', $validator->errors()->all())
                );
            }
        });

        return json_encode($items->all());
    }
}
