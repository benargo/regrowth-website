<?php

namespace App\Services\Discord;

use App\Exceptions\MisconfigurationException;
use App\Services\Discord\Exceptions\RoleNotFoundException;
use App\Services\Discord\Exceptions\UserNotInGuildException;
use App\Services\Discord\Payloads\ChannelMessagesQueryString;
use App\Services\Discord\Payloads\MessagePayload;
use App\Services\Discord\Resources\Channel;
use App\Services\Discord\Resources\GuildMember;
use App\Services\Discord\Resources\Message;
use App\Services\Discord\Resources\Role;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use RuntimeException;

class Discord
{
    /**
     * Create a new instance of the Discord service.
     *
     * @param  DiscordClient  $client  The Discord client for making API requests.
     * @param  array  $config  Optional configuration for the Discord service.
     */
    public function __construct(
        protected readonly DiscordClient $client,
        protected array $config = []
    ) {}

    /**
     * Helper method to retrieve configuration values with a default fallback.
     *
     * @throws MisconfigurationException if a required configuration key is missing.
     */
    private function config(string $key, mixed $default = null): mixed
    {
        // If no default is provided and the key is missing, throw an exception
        if (! $default && ! Arr::has($this->config, $key)) {
            throw new MisconfigurationException("Missing required Discord configuration key: {$key}");
        }

        return Arr::get($this->config, $key, $default);
    }

    // ==================== Channels ====================

    /**
     * Get a channel by ID.
     *
     * Returns a channel object. If the channel is a thread, a thread member object is included in the returned result.
     *
     * @param  string  $channelId  The ID of the channel to retrieve.
     * @return Channel The retrieved channel.
     */
    public function getChannel(string $channelId): Channel
    {
        return Channel::from($this->client->get("channels/{$channelId}")->json());
    }

    /**
     * Returns a list of guild channel objects.
     *
     * Does not include threads.
     *
     * @param  string|null  $guildId  The ID of the guild to list channels from. Defaults to the configured server_id.
     * @return Collection<Channel> A collection of channels in the guild.
     */
    public function getGuildChannels(?string $guildId = null): Collection
    {
        $guildId = $guildId ?? $this->config('server_id', null);

        return Channel::collect($this->client->get("guilds/{$guildId}/channels")->json(), Collection::class);
    }

    // ==================== Guild Members ====================

    /**
     * Returns a guild member object for the specified user.
     *
     * @param  string  $userId  The ID of the user to retrieve guild member data for.
     * @return GuildMember The retrieved guild member data.
     */
    public function getGuildMember(string $userId): GuildMember
    {
        $guildId = $this->config('server_id', null);

        $response = $this->client->get("guilds/{$guildId}/members/{$userId}");

        if ($response->status() === 404) {
            throw new UserNotInGuildException("User {$userId} is not a member of guild {$guildId}");
        }

        if ($response->failed()) {
            throw new RuntimeException('Failed to fetch guild member data: '.$response->body());
        }

        return GuildMember::from($response->json());
    }

    /**
     * Lists members in a guild with cursor-based pagination.
     *
     * Items are augmented with a top-level `id` key (the Discord user ID) so that
     * `CursorPaginator` can build the next cursor via direct key lookup.
     *
     * @param  int  $limit  The max number of members to return (1-1000)
     * @param  Cursor|null  $cursor  The cursor for pagination.
     * @param  string|null  $guildId  The ID of the guild to list members from. Defaults to the configured server_id.
     * @return CursorPaginator A paginator containing the guild members.
     */
    public function getGuildMembers(int $limit = 100, ?Cursor $cursor = null, ?string $guildId = null): CursorPaginator
    {
        $guildId = $guildId ?? $this->config('server_id', null);
        $after = $cursor?->parameter('id');

        $query = ['limit' => min($limit + 1, 1000)];

        if ($after) {
            $query['after'] = $after;
        }

        $response = $this->client->get("guilds/{$guildId}/members", $query);

        if ($response->failed()) {
            throw new RuntimeException('Failed to list guild members: '.$response->body());
        }

        $members = array_map(
            fn (array $member) => ['id' => $member['user']['id']] + $member,
            $response->json(),
        );

        return new CursorPaginator(
            items: $members,
            perPage: $limit,
            cursor: $cursor,
            options: [
                'path' => Paginator::resolveCurrentPath(),
                'cursorName' => 'cursor',
                'parameters' => ['id'],
            ],
        );
    }

    /**
     * List guild members whose username or nickname starts with a provided string.
     *
     * @param  string  $query  The query string to search for in usernames and nicknames.
     * @param  int  $limit  The maximum number of results to return (1-1000).
     * @param  string|null  $guildId  The ID of the guild to search within. Defaults to the configured server_id.
     * @return Collection<GuildMember> A collection of guild members matching the search criteria.
     */
    public function searchGuildMembers(string $query, int $limit = 1, ?string $guildId = null): Collection
    {
        $guildId = $guildId ?? $this->config('server_id', null);

        $response = $this->client->get("guilds/{$guildId}/members/search", [
            'query' => $query,
            'limit' => min(max($limit, 1), 1000),
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Failed to search guild members: '.$response->body());
        }

        return GuildMember::collect($response->json(), Collection::class);
    }

    // ==================== Guild Roles ====================

    /**
     * Returns a list of role objects for the guild
     *
     * @param  string|null  $guildId  The ID of the guild to list roles from. Defaults to the configured server_id.
     * @return Collection<Role> A collection of roles in the guild.
     */
    public function getGuildRoles(?string $guildId = null): Collection
    {
        $guildId = $guildId ?? $this->config('server_id', null);

        $response = $this->client->get("guilds/{$guildId}/roles");

        if ($response->failed()) {
            throw new RuntimeException('Failed to fetch guild roles: '.$response->body());
        }

        return Role::collect($response->json(), Collection::class);
    }

    public function getGuildRole(string $roleId, ?string $guildId = null): Role
    {
        $guildId = $guildId ?? $this->config('server_id', null);

        $response = $this->client->get("guilds/{$guildId}/roles/{$roleId}");

        if ($response->failed()) {
            throw new RuntimeException('Failed to fetch guild role: '.$response->body());
        }

        $data = $response->json();

        if (empty($data)) {
            throw new RoleNotFoundException("Role {$roleId} not found in guild {$guildId}");
        }

        return Role::from($data);
    }

    // ==================== Messages ====================

    /**
     * Retrieves the messages in a channel. Returns an array of message objects from newest to oldest on success.
     *
     * @param  Channel  $channel  The channel to retrieve messages from.
     * @param  ChannelMessagesQueryString  $query  The query parameters for retrieving messages (pagination options).
     * @return Collection<Message> A collection of messages in the channel
     */
    public function getChannelMessages(Channel $channel, ChannelMessagesQueryString $query): Collection
    {
        $response = $this->client->get("channels/{$channel->id}/messages", $query->toArray());

        if ($response->failed()) {
            throw new RuntimeException('Failed to fetch channel messages: '.$response->body());
        }

        return Message::collect($response->json(), Collection::class);
    }

    /**
     * Retreive a message in the channel.
     *
     * @param  Channel  $channel  The channel the message is in.
     * @param  string  $messageId  The ID of the message to retrieve.
     * @return Message The retrieved message.
     */
    public function getChannelMessage(Channel $channel, string $messageId): Message
    {
        $response = $this->client->get("channels/{$channel->id}/messages/{$messageId}");

        if ($response->failed()) {
            throw new RuntimeException('Failed to fetch channel message: '.$response->body());
        }

        return Message::from($response->json());
    }

    /**
     * Post a message to a channel.
     *
     * @param  Channel  $channel  The channel to post the message in.
     * @param  MessagePayload  $payload  The payload containing the message content and options.
     * @return Message The created message.
     */
    public function createMessage(Channel $channel, MessagePayload $payload): Message
    {
        $response = $this->client->post("channels/{$channel->id}/messages", $payload->toArray());

        if ($response->failed()) {
            throw new RuntimeException('Failed to create message: '.$response->body());
        }

        return Message::from($response->json());
    }

    /**
     * Edit a message in the channel.
     *
     * @param  Channel  $channel  The channel the message is in.
     * @param  Message  $message  The message to edit.
     * @param  MessagePayload  $payload  The payload containing the new message content and options.
     * @return Message The updated message.
     */
    public function editMessage(Channel $channel, Message $message, MessagePayload $payload): Message
    {
        $response = $this->client->patch("channels/{$channel->id}/messages/{$message->id}", $payload->toArray());

        if ($response->failed()) {
            throw new RuntimeException('Failed to edit message: '.$response->body());
        }

        return Message::from($response->json());
    }

    /**
     * Delete a message in the channel.
     *
     * @param  Channel  $channel  The channel the message is in.
     * @param  Message  $message  The message to delete.
     */
    public function deleteMessage(Channel $channel, Message $message): void
    {
        $response = $this->client->delete("channels/{$channel->id}/messages/{$message->id}");

        if ($response->failed()) {
            throw new RuntimeException('Failed to delete message: '.$response->body());
        }
    }
}
