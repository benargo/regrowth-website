<?php

namespace App\Services\Blizzard\Exceptions;

use Throwable;

interface BlizzardRequestException extends Throwable
{
    public function getMethod(): string;

    public function getEndpoint(): string;

    public function getBlizzardStatus(): int;

    public function getBlizzardCode(): ?string;

    /** @return array<string, mixed>|null */
    public function getBlizzardBody(): ?array;
}
