<?php

namespace Tests\Support\Discord;

use App\Services\Discord\Discord;
use App\Services\Discord\Enums\MessageType;
use App\Services\Discord\Resources\Channel;
use App\Services\Discord\Resources\Message;
use Mockery\MockInterface;

trait MocksDiscordService
{
    /**
     * Mock the Discord service with getChannel() stubbed to return a
     * Channel built from the given id/name/position. Returns the mock so
     * callers can chain further shouldReceive() expectations (e.g.
     * createMessage(), ->once(), ->with(...)) on the same instance.
     *
     * Pass byDefault: true when a test needs this to be an overridable
     * default expectation (so a later, more specific shouldReceive() call
     * in an individual test can take precedence).
     */
    protected function mockDiscordChannel(string $id = '123456789', ?string $name = null, ?int $position = null, bool $byDefault = false): Discord&MockInterface
    {
        return $this->mock(Discord::class, function (MockInterface $mock) use ($id, $name, $position, $byDefault): void {
            $expectation = $mock->shouldReceive('getChannel')->andReturn(Channel::from(array_filter([
                'id' => $id,
                'name' => $name,
                'position' => $position,
            ], fn (mixed $value): bool => $value !== null)));

            if ($byDefault) {
                $expectation->byDefault();
            }
        });
    }

    /**
     * Build the standard Message payload used when stubbing
     * Discord::createMessage() — the same shape repeated across the suite,
     * varying only the message and channel IDs.
     */
    protected function makeDiscordMessage(string $id = '999999999999999999', string $channelId = '123456789'): Message
    {
        return Message::from([
            'id' => $id,
            'channel_id' => $channelId,
            'timestamp' => now()->toIso8601String(),
            'tts' => false,
            'mention_everyone' => false,
            'mention_roles' => [],
            'attachments' => [],
            'embeds' => [],
            'pinned' => false,
            'type' => MessageType::Default->value,
        ]);
    }
}
