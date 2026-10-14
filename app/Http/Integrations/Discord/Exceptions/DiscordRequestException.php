<?php

namespace App\Http\Integrations\Discord\Exceptions;

use Throwable;

/**
 * Implemented by every exception for a failed Discord response.
 */
interface DiscordRequestException extends Throwable
{
    public function getMethod(): string;

    /**
     * The request endpoint relative to the API base URL, without a leading slash.
     */
    public function getEndpoint(): string;

    public function getDiscordStatus(): int;

    /**
     * Discord's JSON error code (e.g. 10008 Unknown Message), if the body carried one.
     */
    public function getDiscordCode(): ?int;

    /** @return array<string, mixed>|null */
    public function getDiscordBody(): ?array;
}
