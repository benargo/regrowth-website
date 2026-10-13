<?php

namespace Tests\Support\Discord;

use App\Services\Discord\Discord;
use App\Services\Discord\Enums\MessageType;
use App\Services\Discord\Resources\Channel;
use App\Services\Discord\Resources\Message;
use Mockery\MockInterface;
use Saloon\Http\Faking\MockResponse;

/**
 * Discord service mocks and Discord API response bodies for use with Saloon::fake().
 */
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function makeDiscordGuildMemberResponse(array $overrides = []): array
    {
        return array_merge([
            'user' => [
                'id' => '123456789012345678',
                'username' => 'testuser',
                'discriminator' => '0',
                'avatar' => null,
                'flags' => 0,
                'public_flags' => 0,
            ],
            'nick' => null,
            'avatar' => null,
            'banner' => null,
            'roles' => [],
            'deaf' => false,
            'mute' => false,
            'flags' => 0,
        ], $overrides);
    }

    /**
     * A guild member response for the given user id, username and nickname.
     *
     * @return array<string, mixed>
     */
    protected function makeDiscordGuildMemberWithUser(string $userId, string $username, ?string $nickname = null): array
    {
        return $this->makeDiscordGuildMemberResponse([
            'user' => ['id' => $userId, 'username' => $username, 'discriminator' => '0', 'avatar' => null, 'flags' => 0, 'public_flags' => 0],
            'nick' => $nickname,
        ]);
    }

    /**
     * A Discord JSON error response, e.g. (404, 10007, 'Unknown Member').
     */
    protected function makeDiscordErrorResponse(int $status, int $code, string $message): MockResponse
    {
        return MockResponse::make(['message' => $message, 'code' => $code], $status);
    }

    protected function makeDiscordRateLimitResponse(float $retryAfter): MockResponse
    {
        return MockResponse::make(
            ['message' => 'You are being rate limited.', 'retry_after' => $retryAfter, 'global' => false],
            429,
            ['X-RateLimit-Scope' => 'user'],
        );
    }
}
