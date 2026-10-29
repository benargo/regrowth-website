<?php

namespace Database\Factories;

use App\Actions\Events\RefreshEventGameVersion;
use App\Enums\SignupStatus;
use App\Models\Boss;
use App\Models\Character;
use App\Models\DiscordChannel;
use App\Models\Event;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
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
            'channel_id' => DiscordChannel::factory(),
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
        return $this->state(['is_template' => true, 'channel_id' => null]);
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
     * Attach users to the event.
     *
     * @param  array<int, User>|null  $users
     */
    public function withUsers(?array $users = null, int $count = 1): static
    {
        return $this->afterCreating(function (Event $event) use ($users, $count) {
            $users ??= User::factory()->count($count)->create()->all();

            $event->users()->attach(collect($users)->pluck('id'));
        });
    }

    /**
     * Attach a raid in the given game version, and store that game version.
     */
    public function forGameVersion(GameVersion $gameVersion): static
    {
        return $this->afterCreating(function (Event $event) use ($gameVersion): void {
            $raid = Raid::factory()->for(Phase::factory()->forGameVersion($gameVersion))->create();

            $event->raids()->attach($raid->id, ['sort_order' => 1]);
            RefreshEventGameVersion::run($event);
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
