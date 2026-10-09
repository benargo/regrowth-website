<?php

namespace App\Http\Integrations\WarcraftLogs\Exceptions;

use Saloon\GraphQL\Exceptions\GraphQLException as BaseGraphQLException;

/**
 * A Warcraft Logs response that returned GraphQL `errors` (usually at HTTP 200).
 */
class GraphQLException extends BaseGraphQLException implements WarcraftLogsRequestException
{
    /**
     * @return array{host: string|null, status: int, error: string|null}
     */
    public function context(): array
    {
        return [
            'host' => parse_url($this->getPendingRequest()->getUrl(), PHP_URL_HOST) ?: null,
            'status' => $this->getStatus(),
            'error' => $this->getFirstError(),
        ];
    }
}
