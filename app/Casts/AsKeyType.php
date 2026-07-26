<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AsKeyType implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): int|string|null
    {
        if (is_null($value)) {
            return null;
        }

        $keyType = $this->resolveKeyType($key, $attributes);

        return match ($keyType) {
            'int', 'integer' => (int) $value,
            default => (string) $value,
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        $keyType = $this->resolveKeyType($key, $attributes);

        if (in_array($keyType, ['int', 'integer'], true) && ! is_numeric($value)) {
            throw new InvalidArgumentException("Value [{$value}] is not a valid key for an integer-keyed model.");
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveKeyType(string $key, array $attributes): string
    {
        $typeColumn = Str::replaceEnd('_id', '_type', $key);
        $type = Arr::get($attributes, $typeColumn);

        if ($type === null || ! class_exists($type) || ! is_subclass_of($type, Model::class)) {
            return 'string';
        }

        return (new $type)->getKeyType();
    }
}
