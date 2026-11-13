<?php

namespace Database\Factories;

use App\Models\DesktopClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

/**
 * @extends Factory<DesktopClient>
 */
class DesktopClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'name' => fake()->firstName()."'s PC",
            'last_seen_at' => now(),
        ];
    }

    /**
     * Give the client an unrevoked access token bound to it, issued to the desktop OAuth client when one is configured,
     * and a refresh token that expires in 90 days.
     */
    public function linked(): static
    {
        return $this->afterCreating(function (DesktopClient $desktopClient): void {
            $token = Token::forceCreate([
                'id' => Str::random(80),
                'user_id' => $desktopClient->user_id,
                'client_id' => config('grm.desktop_oauth_client_id') ?? Client::factory()->create()->getKey(),
                'name' => $desktopClient->name,
                'scopes' => [],
                'revoked' => false,
                'expires_at' => now()->addDay(),
            ]);

            RefreshToken::forceCreate([
                'id' => Str::random(80),
                'access_token_id' => $token->id,
                'revoked' => false,
                'expires_at' => now()->addDays(90),
            ]);

            $desktopClient->tokens()->create(['access_token_id' => $token->id]);
            $desktopClient->update(['oauth_access_token_id' => $token->id]);
        });
    }
}
