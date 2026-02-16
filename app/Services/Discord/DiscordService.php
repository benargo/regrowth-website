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
     *
     * @param  array<string, mixed>  $query
     */
    protected function get(string $endpoint, array $query = []): Response
    {
        return Http::withHeaders($this->getAuthHeaders())
            ->get(self::BASE_URL.$endpoint, $query);
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
     * Make a PATCH request to the Discord API.
     *
     * @param  array<string, mixed>  $data
     */
    protected function patch(string $endpoint, array $data = []): Response
    {
        return Http::withHeaders($this->getAuthHeaders())
            ->patch(self::BASE_URL.$endpoint, $data);
    }

    /**
     * Make a DELETE request to the Discord API.
     */
    protected function delete(string $endpoint): Response
    {
        return Http::withHeaders($this->getAuthHeaders())
            ->delete(self::BASE_URL.$endpoint);
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
