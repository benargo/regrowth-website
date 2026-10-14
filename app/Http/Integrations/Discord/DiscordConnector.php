<?php

namespace App\Http\Integrations\Discord;

use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Exceptions\MessageNotFoundException;
use App\Http\Integrations\Discord\Exceptions\RateLimitedException;
use App\Http\Integrations\Discord\Exceptions\UserNotInGuildException;
use App\Http\Integrations\Discord\Middleware\GuardRateLimits;
use App\Http\Integrations\Discord\Middleware\RecordRateLimits;
use Saloon\Contracts\Authenticator;
use Saloon\Enums\PipeOrder;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Saloon\Traits\Plugins\HasTimeout;
use Throwable;

/**
 * Saloon connector for the Discord REST API (v10).
 *
 * Records the rate limits Discord reports and throws RateLimitedException for
 * requests to a limited route until it resets.
 */
class DiscordConnector extends Connector
{
    use AcceptsJson;
    use AlwaysThrowOnErrors;
    use HasTimeout;

    /**
     * Discord JSON error code: Unknown Member.
     */
    private const int UNKNOWN_MEMBER = 10007;

    /**
     * Discord JSON error code: Unknown Message.
     */
    private const int UNKNOWN_MESSAGE = 10008;

    protected int $connectTimeout = 3;

    protected int $requestTimeout = 10;

    /**
     * Registers the rate-limit middleware backed by the given rate-limit cache.
     */
    public function __construct(
        protected string $token,
        protected string $userAgent,
        RateLimitCache $rateLimits,
    ) {
        $this->middleware()->onRequest(new GuardRateLimits($rateLimits), 'discordGuardRateLimits', PipeOrder::FIRST);
        $this->middleware()->onResponse(new RecordRateLimits($rateLimits), 'discordRecordRateLimits', PipeOrder::FIRST);
    }

    public function resolveBaseUrl(): string
    {
        return 'https://discord.com/api/v10';
    }

    /**
     * Authenticates with the bot token, prefixed with "Bot ".
     */
    protected function defaultAuth(): Authenticator
    {
        return new TokenAuthenticator($this->token, 'Bot');
    }

    /**
     * Sends the configured User-Agent.
     *
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return ['User-Agent' => $this->userAgent];
    }

    /**
     * Maps a failed response to a Discord exception by its status and JSON error code.
     */
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        $pendingRequest = $response->getPendingRequest();
        $method = $pendingRequest->getMethod()->value;
        $endpoint = ltrim($pendingRequest->getRequest()->resolveEndpoint(), '/');
        $status = $response->status();
        $body = RecordRateLimits::body($response);
        $code = isset($body['code']) && is_numeric($body['code']) ? (int) $body['code'] : null;

        if ($status === 429) {
            return new RateLimitedException(
                $method,
                $endpoint,
                retryAfter: RecordRateLimits::retryAfter($response, $body),
                scope: RecordRateLimits::scope($response),
                global: RecordRateLimits::isGlobal($response, $body),
                response: $response,
                previous: $senderException,
            );
        }

        return match (true) {
            $status === 404 && $code === self::UNKNOWN_MESSAGE => new MessageNotFoundException($response, $method, $endpoint, $code, $body, $senderException),
            $status === 404 && $code === self::UNKNOWN_MEMBER => new UserNotInGuildException($response, $method, $endpoint, $code, $body, $senderException),
            default => new ApiException($response, $method, $endpoint, $code, $body, $senderException),
        };
    }
}
