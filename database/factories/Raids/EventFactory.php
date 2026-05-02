<?php

namespace Database\Factories\Raids;

use App\Models\Character;
use App\Models\Raids\Event;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = Event::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = fake()->dateTimeBetween('now', '+30 days');
        $endTime = Carbon::instance($startTime)->addHours(rand(2, 5));

        return [
            'raid_helper_event_id' => fake()->unique()->numerify('##########'),
            'title' => fake()->words(3, true),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'channel_id' => fake()->numerify('##################'),
        ];
    }

    /**
     * Attach a character as a leader on the event pivot.
     */
    public function withLeader(?Character $character = null): static
    {
        return $this->afterCreating(function (Event $event) use ($character) {
            $character ??= Character::factory()->create();

            $event->characters()->attach($character->id, [
                'is_leader' => true,
                'is_confirmed' => true,
            ]);
        });
    }

    /**
     * Attach a character as a loot councillor on the event pivot.
     */
    public function withLootCouncillor(?Character $character = null): static
    {
        return $this->afterCreating(function (Event $event) use ($character) {
            $character ??= Character::factory()->create();

            $event->characters()->attach($character->id, [
                'is_loot_councillor' => true,
                'is_confirmed' => true,
            ]);
        });
    }
}
