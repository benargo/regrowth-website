<?php

namespace Tests\Support\Blizzard;

trait HasBlizzardTokenMock
{
    /**
     * @return array<string, mixed>
     */
    protected function makeTokenResponse(): array
    {
        return ['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600];
    }
}
