<?php

namespace Tests\Support\Discord;

use App\Http\Integrations\Discord\Enums\MessageType;
use App\Http\Integrations\Discord\Requests\Channels\GetChannelRequest;
use App\Http\Integrations\Discord\Requests\Messages\CreateMessageRequest;
use Illuminate\Pipeline\Pipeline;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;

/**
 * Discord API fakes and response bodies for use with Saloon::fake().
 */
trait MocksDiscordService
{
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
            'joined_at' => '2026-01-01T00:00:00+00:00',
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

    protected function makeDiscordRateLimitResponse(float $retryAfter, string $scope = 'user'): MockResponse
    {
        return MockResponse::make(
            ['message' => 'You are being rate limited.', 'retry_after' => $retryAfter, 'global' => $scope === 'global'],
            429,
            ['X-RateLimit-Scope' => $scope],
        );
    }

    /**
     * @param  array<class-string<Request>, MockResponse|callable>  $fakes
     */
    protected function fakeDiscord(array $fakes): void
    {
        Saloon::fake($fakes);
    }

    /**
     * Run a queued job through its own middleware() and then call handle() through the container.
     */
    protected function runJobThroughMiddleware(object $job): void
    {
        (new Pipeline($this->app))
            ->send($job)
            ->through($job->middleware())
            ->then(fn (object $job) => $this->app->call([$job, 'handle']));
    }

    /**
     * Fake the GET channel request with a channel built from the given id/name/position.
     */
    protected function fakeDiscordChannel(string $id = '123456789', ?string $name = null, ?int $position = null, ?int $type = null): void
    {
        $this->fakeDiscord([
            GetChannelRequest::class => MockResponse::make($this->makeDiscordChannelResponse($id, $name, $position, $type)),
        ]);
    }

    /**
     * Fake the create message request with a message carrying the given id and channel id.
     */
    protected function fakeDiscordMessageCreated(string $id = '999999999999999999', string $channelId = '123456789'): void
    {
        $this->fakeDiscord([
            CreateMessageRequest::class => MockResponse::make($this->makeDiscordMessageResponse($id, $channelId)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeDiscordMessageResponse(string $id = '999999999999999999', string $channelId = '123456789'): array
    {
        return [
            'id' => $id,
            'channel_id' => $channelId,
            'author' => ['id' => '333333333333333333', 'username' => 'Regrowth', 'discriminator' => '0'],
            'content' => '',
            'timestamp' => now()->toIso8601String(),
            'edited_timestamp' => null,
            'tts' => false,
            'mention_everyone' => false,
            'mentions' => [],
            'mention_roles' => [],
            'attachments' => [],
            'embeds' => [],
            'pinned' => false,
            'type' => MessageType::Default->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeDiscordChannelResponse(string $id = '123456789', ?string $name = null, ?int $position = null, ?int $type = null): array
    {
        return array_filter(
            ['id' => $id, 'name' => $name, 'position' => $position, 'type' => $type],
            fn (mixed $value): bool => $value !== null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeDiscordRoleResponse(string $id, string $name, int $position): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'colors' => ['primary_color' => 0],
            'hoist' => false,
            'position' => $position,
            'permissions' => '0',
            'managed' => false,
            'mentionable' => false,
            'flags' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeDiscordEmojiResponse(string $id, string $name, bool $animated = false, bool $available = true): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'roles' => [],
            'require_colons' => true,
            'managed' => false,
            'animated' => $animated,
            'available' => $available,
        ];
    }
}
