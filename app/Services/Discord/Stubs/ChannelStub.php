<?php

namespace App\Services\Discord\Stubs;

use App\Services\Discord\Contracts\Resources\Channel as ChannelContract;

class ChannelStub implements ChannelContract
{
    public function __construct(
        public readonly string $id,
    ) {}
}
