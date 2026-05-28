<?php

namespace App\Services\Discord;

use App\Services\Discord\Contracts\Resources\Channel as ChannelContract;
use App\Services\Discord\Contracts\Resources\Message as MessageContract;
use App\Services\Discord\Exceptions\DiscordRequestException;
use App\Services\Discord\Exceptions\RoleNotFoundException;
use App\Services\Discord\Exceptions\UserNotInGuildException;
use App\Services\Discord\Payloads\ChannelMessagesQueryString;
use App\Services\Discord\Payloads\MessagePayload;
use App\Services\Discord\Resources\Channel as ChannelResource;
use App\Services\Discord\Resources\GuildMember;
use App\Services\Discord\Resources\Message as MessageResource;
use App\Services\Discord\Resources\Role;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class Discord
{
    public function __construct(
        protected readonly DiscordClient $client,
        protected readonly string $serverId,
        protected readonly array $channels = [],
    ) {}

    // ==================== Channels ====================

    /**
     * Get a channel by ID.
     *
     * Returns a channel object. If the channel is a thread, a thread member object is included in the returned result.
     *
     * @param  string  $channelId  The ID of the channel to retrieve.
     * @return ChannelResource The retrieved channel.
     */
    public function getChannel(string $channelId): ChannelResource
    {
        return ChannelResource::from($this->client->get("channels/{$channelId}")->json());
    }

    /**
     * Returns a list of guild channel objects.
     *
     * Does not include threads.
     *
     * @param  string|null  $guildId  The ID of the guild to list channels from. Defaults to the configured server_id.
     * @return Collection<ChannelResource> A collection of channels in the guild.
     */
    public function getGuildChannels(?string $guildId = null): Collection
    {
        $guildId = $guildId ?? $this->serverId;

        return ChannelResource::collect($this->client->get("guilds/{$guildId}/channels")->json(), Collection::class);
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
        $guildId = $this->serverId;

        try {
            $response = $this->client->get("guilds/{$guildId}/members/{$userId}");
        } catch (DiscordRequestException $e) {
            if ($e->status === 404) {
                throw new UserNotInGuildException("User {$userId} is not a member of guild {$guildId}", previous: $e);
            }

            throw $e;
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
        $guildId = $guildId ?? $this->serverId;
        $after = $cursor?->parameter('id');

        // Fetch one extra so CursorPaginator can detect a next page — Discord returns no pagination metadata.
        $query = ['limit' => min($limit + 1, 1000)];

        if ($after) {
            $query['after'] = $after;
        }

        $members = array_map(
            fn (array $member) => ['id' => $member['user']['id']] + $member,
            $this->client->get("guilds/{$guildId}/members", $query)->json(),
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
        $guildId = $guildId ?? $this->serverId;

        return GuildMember::collect(
            $this->client->get("guilds/{$guildId}/members/search", [
                'query' => $query,
                // Discord requires 1 <= limit <= 1000; silently clamp invalid input rather than 422-ing the caller.
                'limit' => min(max($limit, 1), 1000),
            ])->json(),
            Collection::class,
        );
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
        $guildId = $guildId ?? $this->serverId;

        return Role::collect($this->client->get("guilds/{$guildId}/roles")->json(), Collection::class);
    }

    public function getGuildRole(string $roleId, ?string $guildId = null): Role
    {
        $guildId = $guildId ?? $this->serverId;

        try {
            $response = $this->client->get("guilds/{$guildId}/roles/{$roleId}");
        } catch (DiscordRequestException $e) {
            if ($e->status === 404) {
                throw new RoleNotFoundException("Role {$roleId} not found in guild {$guildId}", previous: $e);
            }

            throw $e;
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
     * @param  ChannelContract  $channel  The channel to retrieve messages from.
     * @param  ChannelMessagesQueryString  $query  The query parameters for retrieving messages (pagination options).
     * @return Collection<Message> A collection of messages in the channel
     */
    public function getChannelMessages(ChannelContract $channel, ChannelMessagesQueryString $query): Collection
    {
        return MessageResource::collect(
            $this->client->get("channels/{$channel->id}/messages", $query->toArray())->json(),
            Collection::class,
        );
    }

    /**
     * Retreive a message in the channel.
     *
     * @param  ChannelContract  $channel  The channel the message is in.
     * @param  string  $messageId  The ID of the message to retrieve.
     * @return Message The retrieved message.
     */
    public function getChannelMessage(ChannelContract $channel, string $messageId): MessageResource
    {
        return MessageResource::from(
            $this->client->get("channels/{$channel->id}/messages/{$messageId}")->json(),
        );
    }

    /**
     * Post a message to a channel.
     *
     * @param  ChannelContract  $channel  The channel to post the message in.
     * @param  MessagePayload  $payload  The payload containing the message content and options.
     * @return Message The created message.
     */
    public function createMessage(ChannelContract $channel, MessagePayload $payload): MessageResource
    {
        return MessageResource::from(
            $this->client->post("channels/{$channel->id}/messages", $payload->toArray())->json(),
        );
    }

    /**
     * Edit a message in the channel.
     *
     * @param  Message  $message  The message to edit.
     * @param  MessagePayload  $payload  The payload containing the new message content and options.
     * @return Message The updated message.
     */
    public function editMessage(MessageContract $message, MessagePayload $payload): MessageResource
    {
        return MessageResource::from(
            $this->client->patch("channels/{$message->channel_id}/messages/{$message->id}", $payload->toArray())->json(),
        );
    }

    /**
     * Delete a message in the channel.
     *
     * @param  MessageContract  $message  The message to delete.
     */
    public function deleteMessage(MessageContract $message): void
    {
        $this->client->delete("channels/{$message->channel_id}/messages/{$message->id}");
    }
}
