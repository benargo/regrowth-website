<?php

namespace Database\Factories;

use App\Enums\RecruitmentMacroType;
use App\Models\RecruitmentMacro;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecruitmentMacro>
 */
class RecruitmentMacroFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->words(3, true),
            'type' => RecruitmentMacroType::InGame,
            'content' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate the macro is a Discord post.
     */
    public function discord(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RecruitmentMacroType::Discord,
            'content' => fake()->paragraphs(2, true),
        ]);
    }
}
