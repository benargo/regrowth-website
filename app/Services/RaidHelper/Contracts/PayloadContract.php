<?php

namespace App\Services\RaidHelper\Contracts;

interface PayloadContract
{
    /**
     * Convert the payload object to an array representation that can be sent as the request body in API requests.
     *
     * @return array<string, mixed> An associative array representation of the payload data.
     */
    public function toArray(): array;
}
