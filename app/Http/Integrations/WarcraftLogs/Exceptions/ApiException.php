<?php

namespace App\Http\Integrations\WarcraftLogs\Exceptions;

use Saloon\Exceptions\Request\RequestException;

/**
 * Any failed Warcraft Logs response that carries no GraphQL errors (4xx/5xx, HTML error pages).
 */
class ApiException extends RequestException implements WarcraftLogsRequestException
{
    /**
     * Merged into the log entry when Laravel reports the exception. The host identifies
     * the game version's namespace.
     *
     * @return array{host: string|null, status: int}
     */
    public function context(): array
    {
        return [
            'host' => parse_url($this->getPendingRequest()->getUrl(), PHP_URL_HOST) ?: null,
            'status' => $this->getStatus(),
        ];
    }
}
