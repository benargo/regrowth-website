<?php

namespace Database\Factories;

use App\Models\DiscordNotification;
use App\Models\User;
use App\Notifications\DailyQuestsMessage;
use App\Services\Discord\Payloads\MessagePayload;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

#[UseModel(DiscordNotification::class)]
class DiscordNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => DailyQuestsMessage::class,
            'channel_id' => fake()->numerify('##################'),
            'message_id' => fake()->unique()->numerify('##################'),
            'payload' => MessagePayload::from([
                'content' => fake()->sentence(),
            ]),
            'created_by_user_id' => null,
        ];
    }

    public function createdByUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by_user_id' => $user->id,
        ]);
    }

    /**
     * @param  array<string, list<int|string>>  $entries  e.g. [User::class => [1, 2]]
     */
    public function withRelatedModels(array $entries): static
    {
        return $this->afterCreating(function (DiscordNotification $notification) use ($entries) {
            foreach ($entries as $modelClass => $ids) {
                foreach ($ids as $id) {
                    $notification->relatedModels()->create([
                        'model_type' => $modelClass,
                        'model_id' => $id,
                    ]);
                }
            }
        });
    }
}
