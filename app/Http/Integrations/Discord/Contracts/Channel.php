<?php

namespace App\Http\Integrations\Discord\Contracts;

/**
 * A notifiable Discord channel: a full ChannelData or an id-only ChannelStub.
 */
interface Channel
{
    public string $id { get; }

    /**
     * Get the channel id as the notifiable's unique identifier.
     */
    public function getKey(): string;
}
