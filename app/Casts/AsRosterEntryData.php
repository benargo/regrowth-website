<?php

namespace App\Casts;

use App\Contracts\Data\GuildRosterManager\RosterEntryData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use LogicException;

/**
 * Casts a GRM roster row's JSON payload to the Data class of the row's `kind`, filling `epoch` from the row's
 * `epoch` column. The payload is stored as camelCase JSON without its epoch.
 */
class AsRosterEntryData implements CastsAttributes
{
    /**
     * Rebuild the data object on every read, so it reflects the row's current `kind` and `epoch`.
     */
    public bool $withoutObjectCaching = true;

    /**
     * Hydrate the payload as the Data class of the row's kind.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws LogicException When the row's `kind` attribute is not loaded.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?RosterEntryData
    {
        if ($value === null) {
            return null;
        }

        if (! isset($attributes['kind'])) {
            throw new LogicException('The roster entry payload cast needs the row\'s `kind` attribute loaded.');
        }

        $kind = $attributes['kind'] instanceof RosterEntryKind ? $attributes['kind'] : RosterEntryKind::from($attributes['kind']);

        return $kind->dataClass()::from([
            ...json_decode($value, true, flags: JSON_THROW_ON_ERROR),
            'epoch' => (int) ($attributes['epoch'] ?? 0),
        ]);
    }

    /**
     * Encode a roster row's Data object for storage.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidArgumentException When the value is not a roster row's Data object.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof RosterEntryData) {
            throw new InvalidArgumentException('The payload must be a '.RosterEntryData::class.' instance.');
        }

        return self::serialize($value);
    }

    /**
     * Encode a roster row's Data object as its stored JSON: camelCase keys, without `epoch` or the given keys.
     *
     * @param  array<int, string>  $except
     */
    public static function serialize(RosterEntryData $data, array $except = []): string
    {
        return json_encode(Arr::except($data->toArray(), ['epoch', ...$except]), JSON_THROW_ON_ERROR);
    }
}
