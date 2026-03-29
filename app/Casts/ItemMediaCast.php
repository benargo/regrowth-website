<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * @implements CastsAttributes<ItemMediaCast, ItemMediaCast>
 */
class ItemMediaCast implements CastsAttributes
{
    /**
     * @param  array<int, array{key: string, value: string, file_data_id: int}>  $assets
     */
    public function __construct(
        public readonly int $id = 0,
        public readonly array $assets = [],
    ) {}

    /**
     * @param  array{id: int, assets: array<int, array{key: string, value: string, file_data_id: int}>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            assets: $data['assets'],
        );
    }

    /**
     * Get the URL of the first asset, or null if none exist.
     */
    public function url(): ?string
    {
        return Arr::get($this->assets, '0.value');
    }

    /**
     * @return array{id: int, assets: array<int, array{key: string, value: string, file_data_id: int}>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'assets' => $this->assets,
        ];
    }

    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?self
    {
        if ($value === null) {
            return null;
        }

        $data = is_string($value) ? json_decode($value, true) : $value;

        return self::fromArray($data);
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

        if ($value instanceof self) {
            return json_encode($value->toArray());
        }

        return json_encode($value);
    }
}
