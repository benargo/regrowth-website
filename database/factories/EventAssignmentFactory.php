<?php

namespace Database\Factories;

use App\Models\Boss;
use App\Models\Character;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\EventAssignmentGroup;
use App\Models\Spell;
use App\Models\TargetMarker;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<EventAssignment>
 */
class EventAssignmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = EventAssignment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'boss_id' => null,
            'group_id' => null,
            'sort_order' => fake()->numberBetween(0, 99),
            'left_type' => Character::class,
            'left_value' => fn (array $attributes) => (string) Character::factory()->create()->id,
            'right_type' => null,
            'right_value' => fake()->randomElement(['Main tank', 'AoE', 'Left side', 'kick rotation A']),
        ];
    }

    // ============ Scope states ============

    /**
     * Scope the assignment to a specific boss.
     */
    public function forBoss(Boss $boss): static
    {
        return $this->state(fn (array $attributes) => [
            'boss_id' => $boss->id,
        ]);
    }

    /**
     * Scope the assignment to a specific group.
     */
    public function forGroup(EventAssignmentGroup $group): static
    {
        return $this->state(fn (array $attributes) => [
            'group_id' => $group->id,
        ]);
    }

    // ============ Left-side states ============

    /**
     * Set the left side to a character.
     */
    public function withLeftCharacter(?Character $character = null): static
    {
        return $this->state(function (array $attributes) use ($character) {
            $resolved = $character ?? Character::factory()->create();

            return [
                'left_type' => Character::class,
                'left_value' => (string) $resolved->id,
            ];
        });
    }

    /**
     * Set the left side to a spell.
     */
    public function withLeftSpell(?Spell $spell = null): static
    {
        return $this->state(function (array $attributes) use ($spell) {
            $resolved = $spell ?? Spell::factory()->create();

            return [
                'left_type' => Spell::class,
                'left_value' => (string) $resolved->id,
            ];
        });
    }

    /**
     * Set the left side to a group number (primitive).
     */
    public function withLeftGroupNumber(int $group): static
    {
        return $this->state(fn (array $attributes) => [
            'left_type' => null,
            'left_value' => (string) $group,
        ]);
    }

    /**
     * Set the left side to a custom label (primitive).
     */
    public function withLeftCustom(string $label): static
    {
        return $this->state(fn (array $attributes) => [
            'left_type' => null,
            'left_value' => $label,
        ]);
    }

    // ============ Right-side states ============

    /**
     * Set the right side to a character.
     */
    public function withRightCharacter(?Character $character = null): static
    {
        return $this->state(function (array $attributes) use ($character) {
            $resolved = $character ?? Character::factory()->create();

            return [
                'right_type' => Character::class,
                'right_value' => (string) $resolved->id,
            ];
        });
    }

    /**
     * Set the right side to a target marker.
     */
    public function withRightTargetMarker(?TargetMarker $marker = null): static
    {
        return $this->state(function (array $attributes) use ($marker) {
            $resolved = $marker ?? TargetMarker::factory()->create();

            return [
                'right_type' => TargetMarker::class,
                'right_value' => $resolved->slug,
            ];
        });
    }

    /**
     * Set the right side to a spell.
     */
    public function withRightSpell(?Spell $spell = null): static
    {
        return $this->state(function (array $attributes) use ($spell) {
            $resolved = $spell ?? Spell::factory()->create();

            return [
                'right_type' => Spell::class,
                'right_value' => (string) $resolved->id,
            ];
        });
    }

    /**
     * Set the right side to a group range string (primitive).
     */
    public function withRightGroupRange(string $range): static
    {
        return $this->state(fn (array $attributes) => [
            'right_type' => null,
            'right_value' => $range,
        ]);
    }

    /**
     * Set the right side to a custom label (primitive).
     */
    public function withRightCustom(string $label): static
    {
        return $this->state(fn (array $attributes) => [
            'right_type' => null,
            'right_value' => $label,
        ]);
    }
}
