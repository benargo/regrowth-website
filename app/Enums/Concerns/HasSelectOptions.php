<?php

namespace App\Enums\Concerns;

use Illuminate\Support\Str;

/**
 * Lists a backed enum's cases as value/label pairs for a select input.
 *
 * The label comes from the enum's own label() method when it has one, and is
 * otherwise the capitalised case value.
 */
trait HasSelectOptions
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => method_exists($case, 'label') ? $case->label() : Str::ucfirst($case->value),
            ],
            self::cases(),
        );
    }
}
