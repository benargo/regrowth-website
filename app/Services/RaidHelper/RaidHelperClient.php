<?php

namespace App\Services\RaidHelper;

use App\Services\RaidHelper\Contracts\PayloadContract;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class RaidHelperClient
{
    /**
     * The base URL for the Raid Helper API.
     */
    protected const BASE_URL = 'https://raidhelper.xyz/api/v4/';

    /**
     * Create a new instance of the RaidHelper service.
     *
     * @param  string  $token  The API token for authenticating with the Raid Helper service.
     */
    public function __construct(
        /**
         * The API token for authenticating with the Raid Helper service.
         */
        protected string $token
    ) {}

    /**
     * Make a GET request to the RaidHelper API.
     *
     * @param  string  $endpoint  The API endpoint to send the request to (e.g. '/servers/123/events').
     * @param  array<string, mixed>  $headers  Additional headers to merge with the default authorization headers.
     */
    public function get(string $endpoint, array $headers = []): Response
    {
        return Http::withHeaders($this->headers($headers))
            ->get($this->endpoint($endpoint));
    }

    /**
     * Make a POST request to the RaidHelper API.
     *
     * @param  string  $endpoint  The API endpoint to send the request to (e.g. '/servers/123/events').
     * @param  array<string, mixed>  $headers  Additional headers to merge with the default authorization headers.
     * @param  PayloadContract|null  $payload  An optional payload object that implements the PayloadContract interface, which will be converted to an array and sent as the request body.
     */
    public function post(string $endpoint, array $headers = [], ?PayloadContract $payload = null): Response
    {
        return Http::withHeaders($this->headers($headers))
            ->post($this->endpoint($endpoint), $this->payload($payload));
    }

    /**
     * Make a PATCH request to the RaidHelper API.
     *
     * @param  string  $endpoint  The API endpoint to send the request to (e.g. '/servers/123/events').
     * @param  array<string, mixed>  $headers  Additional headers to merge with the default authorization headers.
     * @param  PayloadContract|null  $payload  An optional payload object that implements the PayloadContract interface, which will be converted to an array and sent as the request body.
     */
    public function patch(string $endpoint, array $headers = [], ?PayloadContract $payload = null): Response
    {
        return Http::withHeaders($this->headers($headers))
            ->patch($this->endpoint($endpoint), $this->payload($payload));
    }

    /**
     * Make a DELETE request to the RaidHelper API.
     *
     * @param  string  $endpoint  The API endpoint to send the request to (e.g. '/servers/123/events').
     * @param  array<string, mixed>  $headers  Additional headers to merge with the default authorization headers.
     */
    public function delete(string $endpoint, array $headers = []): Response
    {
        return Http::withHeaders($this->headers($headers))
            ->delete($this->endpoint($endpoint));
    }

    /**
     * Get the authorization headers for RaidHelper API requests.
     *
     * @param  array<string, string>  $headers  Additional headers to merge with the default authorization headers.
     */
    protected function headers(array $headers = []): array
    {
        return array_merge([
            'Authorization' => $this->token,
            'Content-Type' => 'application/json',
        ], $headers);
    }

    /**
     * Normalize the endpoint by ensuring it does not start with a slash, as the base URL already ends with one.
     *
     * @param  string  $endpoint  The API endpoint to normalize (e.g. '/channels/123/messages' or 'channels/123/messages')
     * @return string The normalized endpoint (e.g. 'channels/123/messages')
     */
    protected function endpoint(string $endpoint): string
    {
        return self::BASE_URL.ltrim($endpoint, '/');
    }

    /**
     * Convert a payload object that implements the PayloadContract interface to an array for sending as the request body.
     *
     * @param  PayloadContract|null  $payload  The payload object to convert to an array.
     * @return array<string, mixed> The payload data as an array.
     */
    protected function payload(?PayloadContract $payload = null): array
    {
        return $payload ? $payload->toArray() : [];
    }
}
