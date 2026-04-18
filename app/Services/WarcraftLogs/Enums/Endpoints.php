<?php

namespace App\Services\WarcraftLogs\Enums;

enum Endpoints: string
{
    /**
     * The base URL for the Warcraft Logs API. This is used for all GraphQL requests and is determined by the environment (retail, classic, fresh, sod).
     * The TOKEN endpoint is used separately for authentication and is not included in this enum.
     * Each environment has its own base URL for API requests, but they all share the same authentication endpoint.
     */
    case WWW = 'https://www.warcraftlogs.com/api/v2/client'; // Retail
    case CLASSIC = 'https://classic.warcraftlogs.com/api/v2/client'; // Mists of Pandaria Classic
    case FRESH = 'https://fresh.warcraftlogs.com/api/v2/client'; // Anniversary
    case SOD = 'https://sod.warcraftlogs.com/api/v2/client'; // Season of Discovery

    /**
     * This endpoint is used for authentication and is the same for all environments.
     */
    case TOKEN = 'https://www.warcraftlogs.com/oauth/token';

    /**
     * Get the URL associated with the enum case. This is used for making API requests to the correct endpoint based on the environment.
     * The TOKEN endpoint is not included in this enum and should be accessed directly when needed for authentication.
     * The default endpoint for API requests is WWW, but it can be overridden by setting the appropriate case in the service configuration.
     * Each case corresponds to a specific environment, allowing for easy switching between them without changing the underlying code that makes API requests.
     * The TOKEN endpoint should be used separately for authentication and is not intended to be used for regular API requests.
     * When making API requests, ensure that you are using the correct endpoint for your environment to avoid issues with rate limits or data access.
     */
    public function url(): string
    {
        return $this->value;
    }

    /**
     * Get the default endpoint URL. This is used as a fallback if no specific endpoint is set in the service configuration. The default is WWW, which is suitable for retail environments. For classic or other environments, the appropriate case should be set in the service configuration to ensure API requests are directed to the correct endpoint.
     * The TOKEN endpoint is not included in this enum and should be accessed directly when needed for authentication.
     * The default endpoint for API requests is WWW, but it can be overridden by setting the appropriate case in the service configuration.
     * Each case corresponds to a specific environment, allowing for easy switching between them without changing the underlying code that makes API requests.
     * The TOKEN endpoint should be used separately for authentication and is not intended to be used for regular API requests.
     * When making API requests, ensure that you are using the correct endpoint for your environment to avoid issues with rate limits or data access.
     */
    public function default(): string
    {
        return self::WWW->value;
    }
}
