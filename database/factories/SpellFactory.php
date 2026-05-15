<?php

namespace Database\Factories;

use App\Enums\AffectType;
use App\Models\Spell;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Spell>
 */
class SpellFactory extends Factory
{
    private $spellNames = [
        'Fireball',
        'Frostbolt',
        'Arcane Missiles',
        'Shadow Bolt',
        'Healing Touch',
        'Rejuvenation',
        'Power Word: Shield',
        'Smite',
        'Wrath',
        'Starfire',
        'Moonfire',
        'Entangling Roots',
        'Insect Swarm',
        'Hurricane',
        'Living Bomb',
        'Pyroblast',
        'Ice Lance',
        'Flamestrike',
        'Blizzard',
        'Cone of Cold',
    ];

    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = Spell::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement($this->spellNames),
            'type' => fake()->randomElement(AffectType::cases()),
        ];
    }
}
