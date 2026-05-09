<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventAssignmentGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventAssignmentGroup>
 */
class EventAssignmentGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'title' => fake()->words(3, true),
            'notes' => fake()->optional()->paragraph(),
            'sort_order' => fake()->numberBetween(0, 255),
        ];
    }

    /**
     * Create a group without notes.
     */
    public function withoutNotes(): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => null,
        ]);
    }
}
