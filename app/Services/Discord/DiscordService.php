<?php

namespace App\Services\Discord;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class DiscordService
{
    /**
     * Base URL for Discord API requests.
     */
    protected const BASE_URL = 'https://discord.com/api/v10';

    /**
     * Bot token for authentication.
     */
    protected string $botToken;

    public function __construct(string $botToken)
    {
        $this->botToken = $botToken;
    }

    /**
     * Make a GET request to the Discord API.
     */
    protected function get(string $endpoint): Response
    {
        return Http::withHeaders($this->getAuthHeaders())
            ->get(self::BASE_URL.$endpoint);
    }

    /**
     * Make a POST request to the Discord API.
     *
     * @param  array<string, mixed>  $data
     */
    protected function post(string $endpoint, array $data = []): Response
    {
        return Http::withHeaders($this->getAuthHeaders())
            ->post(self::BASE_URL.$endpoint, $data);
    }

    /**
     * Get the authorization headers for Discord API requests.
     */
    protected function getAuthHeaders(): array
    {
        return [
            'Authorization' => "Bot {$this->botToken}",
        ];
    }
}
