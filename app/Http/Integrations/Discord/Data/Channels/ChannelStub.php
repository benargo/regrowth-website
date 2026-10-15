<?php

namespace App\Http\Integrations\Discord\Data\Channels;

use App\Http\Integrations\Discord\Contracts\Channel as ChannelContract;
use Illuminate\Notifications\Notifiable;
use Spatie\LaravelData\Data;

/**
 * An id-only notifiable channel, built without an API call (e.g. from a configured channel id).
 */
final class ChannelStub extends Data implements ChannelContract
{
    use Notifiable;

    public function __construct(
        public readonly string $id,
    ) {}

    /**
     * Get the channel id as the notifiable's unique identifier.
     */
    public function getKey(): string
    {
        return $this->id;
    }
}
