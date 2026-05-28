<?php

namespace Tests\Unit\Services\Discord;

use App\Services\Discord\Discord;
use App\Services\Discord\DiscordClient;
use App\Services\Discord\Enums\ChannelType;
use App\Services\Discord\Enums\MessageType;
use App\Services\Discord\Exceptions\DiscordRequestException;
use App\Services\Discord\Exceptions\RateLimitedException;
use App\Services\Discord\Exceptions\RoleNotFoundException;
use App\Services\Discord\Exceptions\UserNotInGuildException;
use App\Services\Discord\Payloads\ChannelMessagesQueryString;
use App\Services\Discord\Payloads\MessagePayload;
use App\Services\Discord\Resources\Channel;
use App\Services\Discord\Resources\GuildMember;
use App\Services\Discord\Resources\Message;
use App\Services\Discord\Resources\Role;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscordTest extends TestCase
{
    private DiscordClient&MockInterface $client;

    private Discord $discord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Mockery::mock(DiscordClient::class);
        $this->discord = new Discord($this->client, '111222333444555666');
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    #[Test]
    public function it_throws_an_invalid_argument_exception_when_server_id_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Discord($this->client, '');
    }

    // -------------------------------------------------------------------------
    // getChannel
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_a_channel_for_a_valid_channel_id(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'id' => '987654321098765432',
            'type' => ChannelType::GUILD_TEXT->value,
            'name' => 'general',
        ]);

        $this->client->expects('get')
            ->with('channels/987654321098765432')
            ->andReturn($response);

        $channel = $this->discord->getChannel('987654321098765432');

        $this->assertInstanceOf(Channel::class, $channel);
        $this->assertSame('987654321098765432', $channel->id);
        $this->assertSame(ChannelType::GUILD_TEXT, $channel->type);
        $this->assertSame('general', $channel->name);
    }

    #[Test]
    public function it_passes_the_channel_id_in_the_api_path(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'id' => '111000111000111000',
            'type' => ChannelType::GUILD_VOICE->value,
        ]);

        $this->client->expects('get')
            ->with('channels/111000111000111000')
            ->andReturn($response);

        $this->discord->getChannel('111000111000111000');
    }

    // -------------------------------------------------------------------------
    // getGuildChannels
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_a_collection_of_channels_for_the_configured_guild(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            ['id' => '111000111000111001', 'type' => ChannelType::GUILD_TEXT->value, 'name' => 'general'],
            ['id' => '111000111000111002', 'type' => ChannelType::GUILD_VOICE->value, 'name' => 'voice-chat'],
        ]);

        $this->client->expects('get')
            ->with('guilds/111222333444555666/channels')
            ->andReturn($response);

        $channels = $this->discord->getGuildChannels();

        $this->assertInstanceOf(Collection::class, $channels);
        $this->assertCount(2, $channels);
        $this->assertSame('111000111000111001', $channels->toArray()[0]['id']);
        $this->assertSame('111000111000111002', $channels->toArray()[1]['id']);
    }

    #[Test]
    public function it_uses_an_explicit_guild_id_when_provided(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            ['id' => '222000222000222001', 'type' => ChannelType::GUILD_TEXT->value, 'name' => 'announcements'],
        ]);

        $this->client->expects('get')
            ->with('guilds/999888777666555444/channels')
            ->andReturn($response);

        $channels = $this->discord->getGuildChannels('999888777666555444');

        $this->assertInstanceOf(Collection::class, $channels);
        $this->assertCount(1, $channels);
    }

    #[Test]
    public function get_guild_channels_uses_the_default_server_id_when_none_is_passed(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([]);

        $this->client->expects('get')
            ->with('guilds/111222333444555666/channels')
            ->andReturn($response);

        $this->discord->getGuildChannels();

        // Assertion is implicit: the client expectation above verifies the correct guild ID is used.
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // getGuildMember
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_a_guild_member_for_a_valid_user_id(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'nick' => 'TestNick',
            'roles' => ['111111111111111111'],
            'deaf' => false,
            'mute' => false,
            'flags' => 0,
        ]);

        $this->client->expects('get')
            ->with('guilds/111222333444555666/members/123456789')
            ->andReturn($response);

        $member = $this->discord->getGuildMember('123456789');

        $this->assertInstanceOf(GuildMember::class, $member);
        $this->assertSame('TestNick', $member->nick);
    }

    #[Test]
    public function it_throws_user_not_in_guild_exception_when_member_returns_404(): void
    {
        $this->client->expects('get')
            ->with('guilds/111222333444555666/members/999999999')
            ->andThrow(new DiscordRequestException('GET', 'guilds/111222333444555666/members/999999999', 404));

        $this->expectException(UserNotInGuildException::class);

        $this->discord->getGuildMember('999999999');
    }

    #[Test]
    public function it_propagates_non_404_discord_exceptions_from_get_guild_member(): void
    {
        $this->client->expects('get')
            ->with('guilds/111222333444555666/members/999999999')
            ->andThrow(new DiscordRequestException('GET', 'guilds/111222333444555666/members/999999999', 500));

        $this->expectException(DiscordRequestException::class);

        $this->discord->getGuildMember('999999999');
    }

    // -------------------------------------------------------------------------
    // getGuildMembers
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_a_cursor_paginator_of_guild_members(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            ['user' => ['id' => '100000000000000001', 'username' => 'alice', 'discriminator' => '0', 'flags' => 0, 'public_flags' => 0], 'nick' => 'Alice', 'roles' => [], 'deaf' => false, 'mute' => false, 'flags' => 0],
            ['user' => ['id' => '100000000000000002', 'username' => 'bob', 'discriminator' => '0', 'flags' => 0, 'public_flags' => 0], 'nick' => 'Bob', 'roles' => [], 'deaf' => false, 'mute' => false, 'flags' => 0],
        ]);

        $this->client->expects('get')
            ->with('guilds/111222333444555666/members', Mockery::subset(['limit' => 3]))
            ->andReturn($response);

        $paginator = $this->discord->getGuildMembers(2);

        $this->assertInstanceOf(CursorPaginator::class, $paginator);
        $this->assertCount(2, $paginator->items());
        $this->assertSame('100000000000000001', $paginator->items()[0]['id']);
    }

    #[Test]
    public function get_guild_members_reports_no_next_page_when_results_equal_limit(): void
    {
        $members = array_fill(0, 100, ['user' => ['id' => '100000000000000001', 'username' => 'alice', 'discriminator' => '0', 'flags' => 0, 'public_flags' => 0], 'nick' => null, 'roles' => [], 'deaf' => false, 'mute' => false, 'flags' => 0]);

        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn($members);

        $this->client->expects('get')
            ->with('guilds/111222333444555666/members', Mockery::subset(['limit' => 101]))
            ->andReturn($response);

        $paginator = $this->discord->getGuildMembers(limit: 100);

        $this->assertFalse($paginator->hasMorePages());
    }

    #[Test]
    public function get_guild_members_reports_a_next_page_when_results_exceed_limit(): void
    {
        $members = array_fill(0, 101, ['user' => ['id' => '100000000000000001', 'username' => 'alice', 'discriminator' => '0', 'flags' => 0, 'public_flags' => 0], 'nick' => null, 'roles' => [], 'deaf' => false, 'mute' => false, 'flags' => 0]);

        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn($members);

        $this->client->expects('get')
            ->with('guilds/111222333444555666/members', Mockery::subset(['limit' => 101]))
            ->andReturn($response);

        $paginator = $this->discord->getGuildMembers(limit: 100);

        $this->assertTrue($paginator->hasMorePages());
    }

    #[Test]
    public function get_guild_members_uses_explicit_guild_id_when_provided(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([]);

        $this->client->expects('get')
            ->with('guilds/999888777666555444/members', Mockery::any())
            ->andReturn($response);

        $this->discord->getGuildMembers(guildId: '999888777666555444');

        // Assertion is implicit: the client expectation above verifies the correct guild ID is used.
        $this->assertTrue(true);
    }

    #[Test]
    public function get_guild_members_passes_cursor_after_parameter_when_cursor_is_provided(): void
    {
        $cursor = new Cursor(['id' => '100000000000000099']);

        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([]);

        $this->client->expects('get')
            ->with('guilds/111222333444555666/members', Mockery::subset(['after' => '100000000000000099']))
            ->andReturn($response);

        $this->discord->getGuildMembers(cursor: $cursor);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // searchGuildMembers
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_a_collection_of_guild_members_matching_the_query(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            ['nick' => 'Alice', 'roles' => [], 'deaf' => false, 'mute' => false, 'flags' => 0],
        ]);

        $this->client->expects('get')
            ->with('guilds/111222333444555666/members/search', Mockery::subset(['query' => 'ali', 'limit' => 5]))
            ->andReturn($response);

        $members = $this->discord->searchGuildMembers('ali', 5);

        $this->assertInstanceOf(Collection::class, $members);
        $this->assertCount(1, $members);
        $this->assertInstanceOf(GuildMember::class, $members->first());
    }

    // -------------------------------------------------------------------------
    // getGuildRoles
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_a_collection_of_roles(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            ['id' => '111111111111111111', 'name' => 'Officer', 'colors' => ['primary_color' => 0], 'hoist' => false, 'position' => 10, 'permissions' => '0', 'managed' => false, 'mentionable' => false, 'flags' => 0],
        ]);

        $this->client->expects('get')
            ->with('guilds/111222333444555666/roles')
            ->andReturn($response);

        $roles = $this->discord->getGuildRoles();

        $this->assertInstanceOf(Collection::class, $roles);
        $this->assertCount(1, $roles);
        $this->assertInstanceOf(Role::class, $roles->first());
        $this->assertSame('Officer', $roles->first()->name);
    }

    // -------------------------------------------------------------------------
    // getGuildRole
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_a_single_role_by_id(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn(
            ['id' => '111111111111111111', 'name' => 'Officer', 'colors' => ['primary_color' => 0], 'hoist' => false, 'position' => 10, 'permissions' => '0', 'managed' => false, 'mentionable' => false, 'flags' => 0]
        );

        $this->client->expects('get')
            ->with('guilds/111222333444555666/roles/111111111111111111')
            ->andReturn($response);

        $role = $this->discord->getGuildRole('111111111111111111');

        $this->assertInstanceOf(Role::class, $role);
        $this->assertSame('Officer', $role->name);
    }

    #[Test]
    public function it_throws_role_not_found_exception_when_role_response_is_empty(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([]);

        $this->client->expects('get')
            ->with('guilds/111222333444555666/roles/000000000000000000')
            ->andReturn($response);

        $this->expectException(RoleNotFoundException::class);

        $this->discord->getGuildRole('000000000000000000');
    }

    #[Test]
    public function it_throws_role_not_found_exception_when_client_returns_404(): void
    {
        $this->client->expects('get')
            ->with('guilds/111222333444555666/roles/000000000000000000')
            ->andThrow(new DiscordRequestException('GET', 'guilds/111222333444555666/roles/000000000000000000', 404));

        $this->expectException(RoleNotFoundException::class);

        $this->discord->getGuildRole('000000000000000000');
    }

    #[Test]
    public function it_propagates_non_404_discord_exceptions_from_get_guild_role(): void
    {
        $this->client->expects('get')
            ->with('guilds/111222333444555666/roles/000000000000000000')
            ->andThrow(new DiscordRequestException('GET', 'guilds/111222333444555666/roles/000000000000000000', 500));

        $this->expectException(DiscordRequestException::class);

        $this->discord->getGuildRole('000000000000000000');
    }

    // -------------------------------------------------------------------------
    // getChannelMessages
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_a_collection_of_messages(): void
    {
        $channel = Channel::from(['id' => '987654321098765432', 'type' => ChannelType::GUILD_TEXT->value]);
        $query = ChannelMessagesQueryString::from(['limit' => 10]);

        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            ['id' => '111111111111111111', 'channel_id' => '987654321098765432', 'timestamp' => '2021-01-01T00:00:00.000000+00:00', 'tts' => false, 'mention_everyone' => false, 'mention_roles' => [], 'attachments' => [], 'embeds' => [], 'pinned' => false, 'type' => MessageType::Default->value],
        ]);

        $this->client->expects('get')
            ->with('channels/987654321098765432/messages', Mockery::any())
            ->andReturn($response);

        $messages = $this->discord->getChannelMessages($channel, $query);

        $this->assertInstanceOf(Collection::class, $messages);
        $this->assertCount(1, $messages);
        $this->assertInstanceOf(Message::class, $messages->first());
    }

    // -------------------------------------------------------------------------
    // getChannelMessage
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_a_single_message_by_id(): void
    {
        $channel = Channel::from(['id' => '987654321098765432', 'type' => ChannelType::GUILD_TEXT->value]);

        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn(
            ['id' => '111111111111111111', 'channel_id' => '987654321098765432', 'timestamp' => '2021-01-01T00:00:00.000000+00:00', 'tts' => false, 'mention_everyone' => false, 'mention_roles' => [], 'attachments' => [], 'embeds' => [], 'pinned' => false, 'type' => MessageType::Default->value]
        );

        $this->client->expects('get')
            ->with('channels/987654321098765432/messages/111111111111111111')
            ->andReturn($response);

        $message = $this->discord->getChannelMessage($channel, '111111111111111111');

        $this->assertInstanceOf(Message::class, $message);
        $this->assertSame('111111111111111111', $message->id);
    }

    // -------------------------------------------------------------------------
    // createMessage
    // -------------------------------------------------------------------------

    #[Test]
    public function it_posts_a_message_and_returns_the_created_message(): void
    {
        $channel = Channel::from(['id' => '987654321098765432', 'type' => ChannelType::GUILD_TEXT->value]);
        $payload = MessagePayload::from(['content' => 'Hello, world!']);

        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn(
            ['id' => '222222222222222222', 'channel_id' => '987654321098765432', 'timestamp' => '2021-01-01T00:00:00.000000+00:00', 'tts' => false, 'mention_everyone' => false, 'mention_roles' => [], 'attachments' => [], 'embeds' => [], 'pinned' => false, 'type' => MessageType::Default->value]
        );

        $this->client->expects('post')
            ->with('channels/987654321098765432/messages', Mockery::any())
            ->andReturn($response);

        $message = $this->discord->createMessage($channel, $payload);

        $this->assertInstanceOf(Message::class, $message);
        $this->assertSame('222222222222222222', $message->id);
    }

    // -------------------------------------------------------------------------
    // editMessage
    // -------------------------------------------------------------------------

    #[Test]
    public function it_patches_a_message_and_returns_the_updated_message(): void
    {
        $existingMessage = Message::from(['id' => '333333333333333333', 'channel_id' => '987654321098765432', 'timestamp' => '2021-01-01T00:00:00.000000+00:00', 'tts' => false, 'mention_everyone' => false, 'mention_roles' => [], 'attachments' => [], 'embeds' => [], 'pinned' => false, 'type' => MessageType::Default->value]);
        $payload = MessagePayload::from(['content' => 'Updated content']);

        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn(
            ['id' => '333333333333333333', 'channel_id' => '987654321098765432', 'content' => 'Updated content', 'timestamp' => '2021-01-01T00:00:00.000000+00:00', 'tts' => false, 'mention_everyone' => false, 'mention_roles' => [], 'attachments' => [], 'embeds' => [], 'pinned' => false, 'type' => MessageType::Default->value]
        );

        $this->client->expects('patch')
            ->with('channels/987654321098765432/messages/333333333333333333', Mockery::any())
            ->andReturn($response);

        $updated = $this->discord->editMessage($existingMessage, $payload);

        $this->assertInstanceOf(Message::class, $updated);
        $this->assertSame('Updated content', $updated->content);
    }

    // -------------------------------------------------------------------------
    // deleteMessage
    // -------------------------------------------------------------------------

    #[Test]
    public function it_deletes_a_message(): void
    {
        $message = Message::from(['id' => '444444444444444444', 'channel_id' => '987654321098765432', 'timestamp' => '2021-01-01T00:00:00.000000+00:00', 'tts' => false, 'mention_everyone' => false, 'mention_roles' => [], 'attachments' => [], 'embeds' => [], 'pinned' => false, 'type' => MessageType::Default->value]);

        $response = Mockery::mock(Response::class);

        $this->client->expects('delete')
            ->with('channels/987654321098765432/messages/444444444444444444')
            ->andReturn($response);

        $this->discord->deleteMessage($message);

        // No exception = pass
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Exception propagation — simple-delegation methods
    // -------------------------------------------------------------------------

    #[Test]
    public function get_channel_propagates_discord_request_exception(): void
    {
        $this->client->expects('get')
            ->with('channels/111')
            ->andThrow(new DiscordRequestException('GET', 'channels/111', 403));

        $this->expectException(DiscordRequestException::class);

        $this->discord->getChannel('111');
    }

    #[Test]
    public function get_guild_channels_propagates_discord_request_exception(): void
    {
        $this->client->expects('get')
            ->with('guilds/111222333444555666/channels')
            ->andThrow(new DiscordRequestException('GET', 'guilds/111222333444555666/channels', 500));

        $this->expectException(DiscordRequestException::class);

        $this->discord->getGuildChannels();
    }

    #[Test]
    public function search_guild_members_propagates_discord_request_exception(): void
    {
        $this->client->expects('get')
            ->with('guilds/111222333444555666/members/search', Mockery::any())
            ->andThrow(new DiscordRequestException('GET', 'guilds/111222333444555666/members/search', 403));

        $this->expectException(DiscordRequestException::class);

        $this->discord->searchGuildMembers('alice');
    }

    #[Test]
    public function get_guild_roles_propagates_discord_request_exception(): void
    {
        $this->client->expects('get')
            ->with('guilds/111222333444555666/roles')
            ->andThrow(new DiscordRequestException('GET', 'guilds/111222333444555666/roles', 500));

        $this->expectException(DiscordRequestException::class);

        $this->discord->getGuildRoles();
    }

    #[Test]
    public function get_guild_members_propagates_discord_request_exception(): void
    {
        $this->client->expects('get')
            ->with('guilds/111222333444555666/members', Mockery::any())
            ->andThrow(new DiscordRequestException('GET', 'guilds/111222333444555666/members', 500));

        $this->expectException(DiscordRequestException::class);

        $this->discord->getGuildMembers();
    }

    #[Test]
    public function get_channel_messages_propagates_discord_request_exception(): void
    {
        $channel = Channel::from(['id' => '987654321098765432', 'type' => ChannelType::GUILD_TEXT->value]);
        $query = ChannelMessagesQueryString::from(['limit' => 10]);

        $this->client->expects('get')
            ->with('channels/987654321098765432/messages', Mockery::any())
            ->andThrow(new DiscordRequestException('GET', 'channels/987654321098765432/messages', 403));

        $this->expectException(DiscordRequestException::class);

        $this->discord->getChannelMessages($channel, $query);
    }

    #[Test]
    public function get_channel_message_propagates_discord_request_exception(): void
    {
        $channel = Channel::from(['id' => '987654321098765432', 'type' => ChannelType::GUILD_TEXT->value]);

        $this->client->expects('get')
            ->with('channels/987654321098765432/messages/111111111111111111')
            ->andThrow(new DiscordRequestException('GET', 'channels/987654321098765432/messages/111111111111111111', 403));

        $this->expectException(DiscordRequestException::class);

        $this->discord->getChannelMessage($channel, '111111111111111111');
    }

    #[Test]
    public function create_message_propagates_discord_request_exception(): void
    {
        $channel = Channel::from(['id' => '987654321098765432', 'type' => ChannelType::GUILD_TEXT->value]);
        $payload = MessagePayload::from(['content' => 'Hello']);

        $this->client->expects('post')
            ->with('channels/987654321098765432/messages', Mockery::any())
            ->andThrow(new DiscordRequestException('POST', 'channels/987654321098765432/messages', 403));

        $this->expectException(DiscordRequestException::class);

        $this->discord->createMessage($channel, $payload);
    }

    #[Test]
    public function edit_message_propagates_discord_request_exception(): void
    {
        $message = Message::from(['id' => '333333333333333333', 'channel_id' => '987654321098765432', 'timestamp' => '2021-01-01T00:00:00.000000+00:00', 'tts' => false, 'mention_everyone' => false, 'mention_roles' => [], 'attachments' => [], 'embeds' => [], 'pinned' => false, 'type' => MessageType::Default->value]);
        $payload = MessagePayload::from(['content' => 'Updated']);

        $this->client->expects('patch')
            ->with('channels/987654321098765432/messages/333333333333333333', Mockery::any())
            ->andThrow(new DiscordRequestException('PATCH', 'channels/987654321098765432/messages/333333333333333333', 500));

        $this->expectException(DiscordRequestException::class);

        $this->discord->editMessage($message, $payload);
    }

    #[Test]
    public function delete_message_propagates_discord_request_exception(): void
    {
        $message = Message::from(['id' => '444444444444444444', 'channel_id' => '987654321098765432', 'timestamp' => '2021-01-01T00:00:00.000000+00:00', 'tts' => false, 'mention_everyone' => false, 'mention_roles' => [], 'attachments' => [], 'embeds' => [], 'pinned' => false, 'type' => MessageType::Default->value]);

        $this->client->expects('delete')
            ->with('channels/987654321098765432/messages/444444444444444444')
            ->andThrow(new DiscordRequestException('DELETE', 'channels/987654321098765432/messages/444444444444444444', 500));

        $this->expectException(DiscordRequestException::class);

        $this->discord->deleteMessage($message);
    }

    // -------------------------------------------------------------------------
    // RateLimitedException bubbles through Discord facade
    // -------------------------------------------------------------------------

    #[Test]
    public function rate_limited_exception_bubbles_through_get_channel(): void
    {
        $this->client->expects('get')
            ->with('channels/111')
            ->andThrow(new RateLimitedException('channels/111', 2.5, 'user'));

        $this->expectException(RateLimitedException::class);

        $this->discord->getChannel('111');
    }
}
