<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

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

        $keyType = $this->resolveKeyType($attributes);

        return $keyType === 'int' ? (int) $value : (string) $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveKeyType(array $attributes): string
    {
        $type = $attributes['commentable_type'] ?? null;

        if ($type === null || ! class_exists($type)) {
            return 'string';
        }

        return (new $type)->getKeyType();
    }
}
