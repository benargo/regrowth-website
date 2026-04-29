<?php

namespace App\Services\Discord\Stubs;

use App\Services\Discord\Contracts\Resources\Message as MessageContract;

class MessageStub implements MessageContract
{
    public function __construct(
        public readonly string $id,
        public readonly string $channel_id,
    ) {}
}
