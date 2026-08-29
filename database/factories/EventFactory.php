<?php

namespace Database\Factories;

use App\Enums\RaidBackground;
use App\Enums\SignupStatus;
use App\Models\Boss;
use App\Models\Character;
use App\Models\Event;
use App\Models\Raid;
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
     * Set the event as a live (non-template) event.
     */
    public function live(): static
    {
        return $this->state(['is_template' => false]);
    }

    /**
     * Set the event as a template.
     */
    public function template(): static
    {
        return $this->state(['is_template' => true]);
    }

    /**
     * Indicate that the event has a background CSS class set.
     */
    public function withBackground(?RaidBackground $background = null): static
    {
        return $this->state(fn (array $attributes) => [
            'background_css_class' => $background ?? fake()->randomElement(RaidBackground::cases()),
        ]);
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
                'signup_status' => SignupStatus::Confirmed->value,
            ]);
        });
    }

    /**
     * Attach raids to the event, in the order given.
     *
     * @param  array<int, Raid>|null  $raids
     */
    public function withRaids(?array $raids = null, int $count = 1): static
    {
        return $this->afterCreating(function (Event $event) use ($raids, $count) {
            $raids ??= Raid::factory()->count($count)->create()->all();

            foreach ($raids as $index => $raid) {
                $event->raids()->attach($raid->id, ['sort_order' => $index + 1]);
            }
        });
    }

    /**
     * Attach bosses to the event, in the order given.
     *
     * @param  array<int, Boss>|null  $bosses
     */
    public function withBosses(?array $bosses = null, int $count = 1): static
    {
        return $this->afterCreating(function (Event $event) use ($bosses, $count) {
            $bosses ??= Boss::factory()->count($count)->create()->all();

            foreach ($bosses as $index => $boss) {
                $event->bosses()->attach($boss->id, ['sort_order' => $index + 1]);
            }
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
                'signup_status' => SignupStatus::Confirmed->value,
            ]);
        });
    }
}
