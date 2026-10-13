<?php

namespace App\Http\Integrations\Discord\Data\Channels;

use App\Http\Integrations\Discord\Contracts\Channel as ChannelContract;
use Spatie\LaravelData\Data;

/**
 * An id-only channel, used where a full channel would cost an API call (e.g. config-keyed channels).
 */
final class ChannelStub extends Data implements ChannelContract
{
    public function __construct(
        public readonly string $id,
    ) {}
}
