<?php

namespace App\Services\Discord\Stubs;

use App\Services\Discord\Contracts\Resources\Channel as ChannelContract;
use Spatie\LaravelData\Data;

class ChannelStub extends Data implements ChannelContract
{
    public function __construct(
        public readonly string $id,
    ) {}
}
