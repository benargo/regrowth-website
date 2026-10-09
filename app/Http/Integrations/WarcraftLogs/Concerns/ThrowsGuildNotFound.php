<?php

namespace App\Http\Integrations\WarcraftLogs\Concerns;

use App\Http\Integrations\WarcraftLogs\Exceptions\GuildNotFoundException;
use Saloon\Http\Response;
use Throwable;

/**
 * Narrows "does not exist" / "not found" GraphQL errors on a guild-scoped
 * request to GuildNotFoundException. Saloon consults the request's hook
 * before the connector's, so every other failure falls through to the
 * connector (GraphQLException or ApiException).
 *
 * The using request must declare `protected readonly int $guildId`.
 */
trait ThrowsGuildNotFound
{
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        if ($response->status() >= 400) {
            return null;
        }

        $exception = $this->guildNotFound($response, $senderException);

        if (! $exception->hasErrorMatching('/does not exist|not found/i')) {
            return null;
        }

        return $exception;
    }

    protected function guildNotFound(Response $response, ?Throwable $previous = null): GuildNotFoundException
    {
        return new GuildNotFoundException($response, "Guild with ID {$this->guildId} not found", previous: $previous);
    }
}
