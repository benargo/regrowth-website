<?php

namespace App\Contracts\Datasets;

/**
 * One step of a dataset's setup wizard, which follows the details form.
 */
interface SetupStep
{
    public function label(): string;

    /**
     * The React section component that renders this step.
     */
    public function component(): string;

    /**
     * @return array{value: string, label: string, component: string}
     */
    public function toOption(): array;

    /**
     * The step after this one, or null when this is the last step.
     */
    public function next(): ?self;

    /**
     * The step before this one, or null when this is the first step.
     */
    public function previous(): ?self;

    /**
     * The step the wizard opens on after the details form.
     */
    public static function first(): self;

    /**
     * Every step in wizard order, for the stepper and the Edit page sections.
     *
     * @return list<array{value: string, label: string, component: string}>
     */
    public static function options(): array;
}
