<?php

namespace App\Http\Integrations\Discord;

use App\Contracts\HasDiscordAvatars;
use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Exceptions\AvatarNotFoundException;
use App\Http\Integrations\Discord\Middleware\RecordRateLimits;
use Saloon\Http\Connector;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Saloon\Traits\Plugins\HasTimeout;
use Throwable;

/**
 * Unauthenticated Saloon connector for the Discord CDN (cdn.discordapp.com).
 */
class DiscordCdnConnector extends Connector implements HasDiscordAvatars
{
    use AlwaysThrowOnErrors;
    use HasTimeout;

    protected int $connectTimeout = 3;

    protected int $requestTimeout = 10;

    public function resolveBaseUrl(): string
    {
        return self::CDN_BASE_URL;
    }

    /**
     * Maps a failed response to AvatarNotFoundException for a 404, otherwise ApiException.
     */
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        $pendingRequest = $response->getPendingRequest();
        $method = $pendingRequest->getMethod()->value;
        $endpoint = ltrim($pendingRequest->getRequest()->resolveEndpoint(), '/');
        $body = RecordRateLimits::body($response);

        if ($response->status() === 404) {
            return new AvatarNotFoundException($response, $method, $endpoint, null, $body, $senderException);
        }

        $code = isset($body['code']) && is_numeric($body['code']) ? (int) $body['code'] : null;

        return new ApiException($response, $method, $endpoint, $code, $body, $senderException);
    }
}
