<?php

namespace App\Casts;

use App\Services\WarcraftLogs\ValueObjects\Expansion;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AsExpansion implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Expansion
    {
        if ($value === null) {
            return null;
        }

        $data = is_string($value) ? json_decode($value, associative: true) : (array) $value;

        return Expansion::fromArray($data);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof Expansion) {
            throw new InvalidArgumentException('Value must be an instance of Expansion.');
        }

        return json_encode($value->toArray());
    }
}
