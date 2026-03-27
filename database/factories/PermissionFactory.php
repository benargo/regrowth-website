<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(2),
            'guard_name' => 'web',
            'group' => null,
        ];
    }

    /**
     * Indicate this permission belongs to a specific group.
     */
    public function inGroup(string $group): static
    {
        return $this->state(fn () => ['group' => $group]);
    }
}
