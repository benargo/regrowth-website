<?php

namespace Database\Factories;

use App\Http\Integrations\Discord\Data\Messages\MessagePayloadData;
use App\Models\DiscordNotification;
use App\Models\User;
use App\Notifications\RaidAssignmentsPublished;
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
            'type' => RaidAssignmentsPublished::class,
            'channel_id' => fake()->numerify('##################'),
            'message_id' => fake()->unique()->numerify('##################'),
            'payload' => MessagePayloadData::from([
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
     * @param  list<array{model_id: int|string, model_type: string}>  $entries
     */
    public function withRelatedModels(array $entries): static
    {
        return $this->afterCreating(function (DiscordNotification $notification) use ($entries) {
            $notification->relatedModels()->createMany($entries);
        });
    }
}
