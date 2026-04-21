<?php

namespace App\Casts;

use App\Services\WarcraftLogs\ValueObjects\ExpansionData;
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
    public function get(Model $model, string $key, mixed $value, array $attributes): ?ExpansionData
    {
        if ($value === null) {
            return null;
        }

        $data = is_string($value) ? json_decode($value, associative: true) : (array) $value;

        return ExpansionData::from($data);
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

        if (! $value instanceof ExpansionData) {
            throw new InvalidArgumentException('Value must be an instance of ExpansionData.');
        }

        return json_encode($value->toArray());
    }
}
