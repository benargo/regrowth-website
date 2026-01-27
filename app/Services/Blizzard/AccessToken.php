<?php

namespace App\Services\Blizzard;

use App\Exceptions\ExpiredTokenException;
use Illuminate\Support\Carbon;

class AccessToken
{
    public readonly Carbon $expiresAt;

    public function __construct(
        public readonly string $token,
        public readonly string $tokenType,
        public readonly int $expiresIn,
        public readonly Carbon $createdAt = new Carbon,
    ) {
        $this->expiresAt = $this->createdAt->copy()->addSeconds($this->expiresIn);
    }

    public static function fromResponse(array $data): self
    {
        return new self(
            token: $data['access_token'],
            tokenType: $data['token_type'],
            expiresIn: (int) $data['expires_in'],
        );
    }

    public function isExpired(): bool
    {
        return $this->expiresAt->isPast();
    }

    public function getToken(): string
    {
        if ($this->isExpired()) {
            throw new ExpiredTokenException('Token has expired');
        }

        return $this->token;
    }
}
