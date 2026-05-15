<?php

namespace Database\Factories;

use App\Models\TargetMarker;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<TargetMarker>
 */
class TargetMarkerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = TargetMarker::class;

    private static array $slugs = ['skull', 'cross', 'square', 'moon', 'triangle', 'diamond', 'circle', 'star'];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->randomElement(self::$slugs);

        return [
            'slug' => $slug,
            'name' => ucfirst($slug),
        ];
    }
}
