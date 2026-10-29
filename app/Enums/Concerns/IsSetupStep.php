<?php

namespace App\Enums\Concerns;

use App\Contracts\Datasets\SetupStep;
use Illuminate\Support\Str;

/**
 * Implements SetupStep for a string-backed enum whose cases are in wizard
 * order. The using enum supplies only its cases and label().
 *
 * @phpstan-require-implements SetupStep
 */
trait IsSetupStep
{
    /**
     * The section component that renders this step, named after the slug:
     * "races-and-classes" is RacesAndClassesSection.
     */
    public function component(): string
    {
        return Str::studly($this->value).'Section';
    }

    public static function first(): self
    {
        return self::cases()[0];
    }

    public function next(): ?self
    {
        return self::cases()[$this->position() + 1] ?? null;
    }

    public function previous(): ?self
    {
        return self::cases()[$this->position() - 1] ?? null;
    }

    /**
     * @return array{value: string, label: string, component: string}
     */
    public function toOption(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'component' => $this->component(),
        ];
    }

    /**
     * @return list<array{value: string, label: string, component: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $step): array => $step->toOption())
            ->all();
    }

    /**
     * This step's zero-based position in wizard order.
     */
    private function position(): int
    {
        return array_search($this, self::cases(), true);
    }
}
