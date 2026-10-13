<?php

namespace App\Http\Integrations\Discord\Contracts;

/**
 * Anything that identifies a Discord channel: a full ChannelData or an id-only ChannelStub.
 */
interface Channel
{
    public string $id { get; }
}
