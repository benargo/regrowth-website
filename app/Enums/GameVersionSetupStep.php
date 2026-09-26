<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * The steps of the new game version wizard that follow the details form, in
 * case order. The value is the URL slug and the matching Edit page anchor,
 * and names the React section component that renders the step.
 */
enum GameVersionSetupStep: string
{
    case RACES_AND_CLASSES = 'races-and-classes';
    case PHASES = 'phases';

    public function label(): string
    {
        return match ($this) {
            self::RACES_AND_CLASSES => 'Races and classes',
            self::PHASES => 'Phases and raids',
        };
    }

    /**
     * The section component in resources/js/Components/GameVersions that
     * renders this step, named after the slug: "races-and-classes" is
     * RacesAndClassesSection.
     */
    public function component(): string
    {
        return Str::studly($this->value).'Section';
    }

    /**
     * The step the wizard opens on after the details form.
     */
    public static function first(): self
    {
        return self::cases()[0];
    }

    /**
     * The step after this one, or null when this is the last step.
     */
    public function next(): ?self
    {
        return self::cases()[$this->position() + 1] ?? null;
    }

    /**
     * The step before this one, or null when this is the first step.
     */
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
     * Every step in wizard order, for the stepper and the Edit page sections.
     *
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
