<?php

namespace Database\Factories;

use App\Models\DesktopClient;
use App\Models\DesktopClientToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Token;

/**
 * @extends Factory<DesktopClientToken>
 */
class DesktopClientTokenFactory extends Factory
{
    /**
     * Define the model's default state: an unrevoked access token of the client's user, issued to the desktop OAuth client when one is configured.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'desktop_client_id' => DesktopClient::factory(),
            'access_token_id' => fn (array $attributes): string => Token::forceCreate([
                'id' => Str::random(80),
                'user_id' => DesktopClient::findOrFail($attributes['desktop_client_id'])->user_id,
                'client_id' => config('services.grm.desktop_oauth_client_id') ?? Client::factory()->create()->getKey(),
                'name' => null,
                'scopes' => ['grm:upload', 'grm:targets'],
                'revoked' => false,
                'expires_at' => now()->addHour(),
            ])->id,
        ];
    }
}
