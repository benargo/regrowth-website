<?php

namespace App\Services\Discord;

use App\Services\Discord\Exceptions\DiscordRequestException;
use App\Services\Discord\Exceptions\MessageNotFoundException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class DiscordClient
{
    private const BASE_URL = 'https://discord.com/api/v10/';

    public function __construct(
        private string $token
    ) {}

    public function get(string $endpoint, array $query = []): Response
    {
        return $this->request('GET', $endpoint, query: $query);
    }

    public function post(string $endpoint, array $data = []): Response
    {
        return $this->request('POST', $endpoint, body: $data);
    }

    public function patch(string $endpoint, array $data = []): Response
    {
        return $this->request('PATCH', $endpoint, body: $data);
    }

    public function delete(string $endpoint): Response
    {
        return $this->request('DELETE', $endpoint);
    }

    protected function request(string $method, string $endpoint, array $body = [], array $query = []): Response
    {
        $normalised = ltrim($endpoint, '/');
        $url = self::BASE_URL.$normalised;

        $http = Http::withHeaders($this->getAuthHeaders());

        $response = match ($method) {
            'GET' => $http->get($url, $query),
            'POST' => $http->post($url, $body),
            'PATCH' => $http->patch($url, $body),
            'DELETE' => $http->delete($url),
        };

        if ($response->successful()) {
            return $response;
        }

        $this->throwForStatus($method, $normalised, $response);
    }

    protected function throwForStatus(string $method, string $endpoint, Response $response): never
    {
        $body = $response->json();
        $code = is_array($body) && isset($body['code']) ? (int) $body['code'] : null;
        $bodyArr = is_array($body) ? $body : null;

        // 404s on message endpoints get a more specific exception so callers can distinguish
        // a stale message_id from any other failure.
        if ($response->status() === 404 && preg_match('#^channels/[^/]+/messages/[^/]+$#', $endpoint) === 1) {
            throw new MessageNotFoundException($method, $endpoint, 404, $code, $bodyArr);
        }

        throw new DiscordRequestException($method, $endpoint, $response->status(), $code, $bodyArr);
    }

    protected function getAuthHeaders(): array
    {
        return [
            'Authorization' => "Bot {$this->token}",
        ];
    }
}
