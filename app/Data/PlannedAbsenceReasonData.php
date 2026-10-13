<?php

namespace App\Data;

use App\Enums\PlannedAbsenceReason;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class PlannedAbsenceReasonData extends Data
{
    public function __construct(
        public readonly PlannedAbsenceReason $key,
        public readonly string $label,
        public readonly string $description,
        public readonly string $icon,
    ) {}

    /**
     * Build the presentation data for an absence reason.
     */
    public static function fromReason(PlannedAbsenceReason $reason): self
    {
        return new self(
            key: $reason,
            label: $reason->value,
            description: $reason->description(),
            icon: $reason->icon(),
        );
    }

    /**
     * List every reason for the absence form's radio cards.
     *
     * @return Collection<int, self>
     */
    public static function options(): Collection
    {
        return collect(PlannedAbsenceReason::cases())->map(fn (PlannedAbsenceReason $reason): self => self::fromReason($reason));
    }

    /**
     * Store the reason as its enum value rather than laravel-data's default JSON.
     *
     * @param  array<int, string>  $arguments
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            /**
             * Rebuild the data object on every read, so a model given an enum
             * case or value string still hands back a PlannedAbsenceReasonData.
             */
            public bool $withoutObjectCaching = true;

            /**
             * @param  array<string, mixed>  $attributes
             */
            public function get(Model $model, string $key, mixed $value, array $attributes): ?PlannedAbsenceReasonData
            {
                if ($value === null) {
                    return null;
                }

                return PlannedAbsenceReasonData::fromReason(PlannedAbsenceReason::from($value));
            }

            /**
             * @param  array<string, mixed>  $attributes
             */
            public function set(Model $model, string $key, mixed $value, array $attributes): ?string
            {
                return match (true) {
                    $value === null => null,
                    $value instanceof PlannedAbsenceReasonData => $value->key->value,
                    $value instanceof PlannedAbsenceReason => $value->value,
                    default => PlannedAbsenceReason::from($value)->value,
                };
            }
        };
    }
}
