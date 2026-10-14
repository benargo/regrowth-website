<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Channels;

use App\Http\Integrations\Discord\Contracts\Channel as ChannelContract;
use App\Http\Integrations\Discord\Data\Channels\ChannelData;
use App\Http\Integrations\Discord\Enums\ChannelType;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

#[Group('discord-integration')]
class ChannelDataTest extends TestCase
{
    #[Test]
    public function channel_can_be_constructed_directly(): void
    {
        $channel = new ChannelData(
            id: '123456789012345678',
            type: Optional::create(),
            guildId: Optional::create(),
            position: Optional::create(),
            name: Optional::create(),
            topic: Optional::create(),
            nsfw: Optional::create(),
            lastMessageId: Optional::create(),
            bitrate: Optional::create(),
            userLimit: Optional::create(),
            rateLimitPerUser: Optional::create(),
            ownerId: Optional::create(),
            applicationId: Optional::create(),
            managed: Optional::create(),
            parentId: Optional::create(),
            lastPinTimestamp: Optional::create(),
            rtcRegion: Optional::create(),
            videoQualityMode: Optional::create(),
            messageCount: Optional::create(),
            memberCount: Optional::create(),
            defaultAutoArchiveDuration: Optional::create(),
            permissions: Optional::create(),
            flags: Optional::create(),
            totalMessageSent: Optional::create(),
            appliedTags: Optional::create(),
            defaultSortOrder: Optional::create(),
            defaultForumLayout: Optional::create(),
            defaultThreadRateLimitPerUser: Optional::create(),
            permissionOverwrites: Optional::create(),
            recipients: Optional::create(),
            icon: Optional::create(),
            availableTags: Optional::create(),
            defaultReactionEmoji: Optional::create(),
            threadMetadata: Optional::create(),
            member: Optional::create(),
        );

        $this->assertSame('123456789012345678', $channel->id);
        $this->assertInstanceOf(Optional::class, $channel->type);
        $this->assertInstanceOf(Optional::class, $channel->guildId);
        $this->assertInstanceOf(Optional::class, $channel->position);
        $this->assertInstanceOf(Optional::class, $channel->name);
        $this->assertInstanceOf(Optional::class, $channel->topic);
        $this->assertInstanceOf(Optional::class, $channel->nsfw);
        $this->assertInstanceOf(Optional::class, $channel->lastMessageId);
        $this->assertInstanceOf(Optional::class, $channel->bitrate);
        $this->assertInstanceOf(Optional::class, $channel->userLimit);
        $this->assertInstanceOf(Optional::class, $channel->rateLimitPerUser);
        $this->assertInstanceOf(Optional::class, $channel->ownerId);
        $this->assertInstanceOf(Optional::class, $channel->applicationId);
        $this->assertInstanceOf(Optional::class, $channel->managed);
        $this->assertInstanceOf(Optional::class, $channel->parentId);
        $this->assertInstanceOf(Optional::class, $channel->lastPinTimestamp);
        $this->assertInstanceOf(Optional::class, $channel->rtcRegion);
        $this->assertInstanceOf(Optional::class, $channel->videoQualityMode);
        $this->assertInstanceOf(Optional::class, $channel->messageCount);
        $this->assertInstanceOf(Optional::class, $channel->memberCount);
        $this->assertInstanceOf(Optional::class, $channel->defaultAutoArchiveDuration);
        $this->assertInstanceOf(Optional::class, $channel->permissions);
        $this->assertInstanceOf(Optional::class, $channel->flags);
        $this->assertInstanceOf(Optional::class, $channel->totalMessageSent);
        $this->assertInstanceOf(Optional::class, $channel->appliedTags);
        $this->assertInstanceOf(Optional::class, $channel->defaultSortOrder);
        $this->assertInstanceOf(Optional::class, $channel->defaultForumLayout);
        $this->assertInstanceOf(Optional::class, $channel->defaultThreadRateLimitPerUser);
        $this->assertInstanceOf(Optional::class, $channel->permissionOverwrites);
        $this->assertInstanceOf(Optional::class, $channel->recipients);
        $this->assertInstanceOf(Optional::class, $channel->icon);
        $this->assertInstanceOf(Optional::class, $channel->availableTags);
        $this->assertInstanceOf(Optional::class, $channel->defaultReactionEmoji);
        $this->assertInstanceOf(Optional::class, $channel->threadMetadata);
        $this->assertInstanceOf(Optional::class, $channel->member);
    }

    // ==================== construction from minimal payload ====================

    /**
     * @return array<string, mixed>
     */
    private function minimalPayload(): array
    {
        return [
            'id' => '123456789012345678',
            'type' => ChannelType::GuildText->value,
        ];
    }

    #[Test]
    public function it_constructs_from_minimal_payload(): void
    {
        $channel = ChannelData::from($this->minimalPayload());

        $this->assertSame('123456789012345678', $channel->id);
        $this->assertSame(ChannelType::GuildText, $channel->type);
    }

    #[Test]
    public function all_optional_fields_default_to_optional(): void
    {
        $channel = ChannelData::from($this->minimalPayload());

        $this->assertInstanceOf(Optional::class, $channel->guildId);
        $this->assertInstanceOf(Optional::class, $channel->position);
        $this->assertInstanceOf(Optional::class, $channel->name);
        $this->assertInstanceOf(Optional::class, $channel->topic);
        $this->assertInstanceOf(Optional::class, $channel->nsfw);
        $this->assertInstanceOf(Optional::class, $channel->lastMessageId);
        $this->assertInstanceOf(Optional::class, $channel->bitrate);
        $this->assertInstanceOf(Optional::class, $channel->userLimit);
        $this->assertInstanceOf(Optional::class, $channel->rateLimitPerUser);
        $this->assertInstanceOf(Optional::class, $channel->ownerId);
        $this->assertInstanceOf(Optional::class, $channel->applicationId);
        $this->assertInstanceOf(Optional::class, $channel->managed);
        $this->assertInstanceOf(Optional::class, $channel->parentId);
        $this->assertInstanceOf(Optional::class, $channel->lastPinTimestamp);
        $this->assertInstanceOf(Optional::class, $channel->rtcRegion);
        $this->assertInstanceOf(Optional::class, $channel->videoQualityMode);
        $this->assertInstanceOf(Optional::class, $channel->messageCount);
        $this->assertInstanceOf(Optional::class, $channel->memberCount);
        $this->assertInstanceOf(Optional::class, $channel->defaultAutoArchiveDuration);
        $this->assertInstanceOf(Optional::class, $channel->permissions);
        $this->assertInstanceOf(Optional::class, $channel->flags);
        $this->assertInstanceOf(Optional::class, $channel->totalMessageSent);
        $this->assertInstanceOf(Optional::class, $channel->appliedTags);
        $this->assertInstanceOf(Optional::class, $channel->defaultSortOrder);
        $this->assertInstanceOf(Optional::class, $channel->defaultForumLayout);
        $this->assertInstanceOf(Optional::class, $channel->defaultThreadRateLimitPerUser);
        $this->assertInstanceOf(Optional::class, $channel->permissionOverwrites);
        $this->assertInstanceOf(Optional::class, $channel->recipients);
        $this->assertInstanceOf(Optional::class, $channel->icon);
        $this->assertInstanceOf(Optional::class, $channel->availableTags);
        $this->assertInstanceOf(Optional::class, $channel->defaultReactionEmoji);
        $this->assertInstanceOf(Optional::class, $channel->threadMetadata);
        $this->assertInstanceOf(Optional::class, $channel->member);
    }

    // ==================== channel type enum ====================

    #[Test]
    public function it_hydrates_the_channel_type_enum(): void
    {
        foreach (ChannelType::cases() as $case) {
            $channel = ChannelData::from(['id' => '1', 'type' => $case->value]);
            $this->assertSame($case, $channel->type);
        }
    }

    // ==================== scalar optional fields ====================

    #[Test]
    public function it_stores_all_scalar_optional_fields(): void
    {
        $channel = ChannelData::from([
            ...$this->minimalPayload(),
            'guild_id' => '111',
            'position' => 3,
            'name' => 'general',
            'topic' => 'Welcome!',
            'nsfw' => false,
            'last_message_id' => '999',
            'bitrate' => 64000,
            'user_limit' => 10,
            'rate_limit_per_user' => 30,
            'owner_id' => '222',
            'application_id' => '333',
            'managed' => true,
            'parent_id' => '444',
            'last_pin_timestamp' => '2024-01-01T00:00:00Z',
            'rtc_region' => 'us-east',
            'video_quality_mode' => 1,
            'message_count' => 42,
            'member_count' => 5,
            'default_auto_archive_duration' => 1440,
            'permissions' => '8',
            'flags' => 0,
            'total_message_sent' => 100,
            'default_sort_order' => 0,
            'default_forum_layout' => 1,
            'default_thread_rate_limit_per_user' => 60,
            'icon' => 'abc123hash',
        ]);

        $this->assertSame('111', $channel->guildId);
        $this->assertSame(3, $channel->position);
        $this->assertSame('general', $channel->name);
        $this->assertSame('Welcome!', $channel->topic);
        $this->assertFalse($channel->nsfw);
        $this->assertSame('999', $channel->lastMessageId);
        $this->assertSame(64000, $channel->bitrate);
        $this->assertSame(10, $channel->userLimit);
        $this->assertSame(30, $channel->rateLimitPerUser);
        $this->assertSame('222', $channel->ownerId);
        $this->assertSame('333', $channel->applicationId);
        $this->assertTrue($channel->managed);
        $this->assertSame('444', $channel->parentId);
        $this->assertSame('2024-01-01T00:00:00Z', $channel->lastPinTimestamp);
        $this->assertSame('us-east', $channel->rtcRegion);
        $this->assertSame(1, $channel->videoQualityMode);
        $this->assertSame(42, $channel->messageCount);
        $this->assertSame(5, $channel->memberCount);
        $this->assertSame(1440, $channel->defaultAutoArchiveDuration);
        $this->assertSame('8', $channel->permissions);
        $this->assertSame(0, $channel->flags);
        $this->assertSame(100, $channel->totalMessageSent);
        $this->assertSame(0, $channel->defaultSortOrder);
        $this->assertSame(1, $channel->defaultForumLayout);
        $this->assertSame(60, $channel->defaultThreadRateLimitPerUser);
        $this->assertSame('abc123hash', $channel->icon);
    }

    // ==================== nullable fields ====================

    #[Test]
    public function it_accepts_null_for_default_reaction_emoji(): void
    {
        $channel = ChannelData::from([...$this->minimalPayload(), 'default_reaction_emoji' => null]);
        $this->assertNull($channel->defaultReactionEmoji);
    }

    #[Test]
    public function it_stores_null_for_nullable_optional_fields(): void
    {
        $channel = ChannelData::from([...$this->minimalPayload(), 'topic' => null]);
        $this->assertNull($channel->topic);

        $channel = ChannelData::from([...$this->minimalPayload(), 'last_message_id' => null]);
        $this->assertNull($channel->lastMessageId);

        $channel = ChannelData::from([...$this->minimalPayload(), 'last_pin_timestamp' => null]);
        $this->assertNull($channel->lastPinTimestamp);

        $channel = ChannelData::from([...$this->minimalPayload(), 'rtc_region' => null]);
        $this->assertNull($channel->rtcRegion);

        $channel = ChannelData::from([...$this->minimalPayload(), 'parent_id' => null]);
        $this->assertNull($channel->parentId);

        $channel = ChannelData::from([...$this->minimalPayload(), 'default_sort_order' => null]);
        $this->assertNull($channel->defaultSortOrder);

        $channel = ChannelData::from([...$this->minimalPayload(), 'icon' => null]);
        $this->assertNull($channel->icon);

        $channel = ChannelData::from([...$this->minimalPayload(), 'application_id' => null]);
        $this->assertNull($channel->applicationId);

        $channel = ChannelData::from([...$this->minimalPayload(), 'name' => null]);
        $this->assertNull($channel->name);
    }

    // ==================== array fields ====================

    #[Test]
    public function it_stores_array_fields(): void
    {
        $tags = [['id' => '1', 'name' => 'bug'], ['id' => '2', 'name' => 'help']];
        $overwrites = [['id' => '10', 'type' => 0, 'allow' => '0', 'deny' => '8']];

        $channel = ChannelData::from([
            ...$this->minimalPayload(),
            'applied_tags' => ['1', '2'],
            'available_tags' => $tags,
            'permission_overwrites' => $overwrites,
            'recipients' => [['id' => '50', 'username' => 'Thrall']],
            'default_reaction_emoji' => ['emoji_id' => '77', 'emoji_name' => null],
            'thread_metadata' => ['archived' => false, 'auto_archive_duration' => 60],
            'member' => ['user_id' => '99', 'join_timestamp' => '2024-01-01T00:00:00Z'],
        ]);

        $this->assertSame(['1', '2'], $channel->appliedTags);
        $this->assertSame($tags, $channel->availableTags);
        $this->assertSame($overwrites, $channel->permissionOverwrites);
        $this->assertSame([['id' => '50', 'username' => 'Thrall']], $channel->recipients);
        $this->assertSame(['emoji_id' => '77', 'emoji_name' => null], $channel->defaultReactionEmoji);
        $this->assertSame(['archived' => false, 'auto_archive_duration' => 60], $channel->threadMetadata);
        $this->assertSame(['user_id' => '99', 'join_timestamp' => '2024-01-01T00:00:00Z'], $channel->member);
    }

    // ==================== validation rules ====================

    #[Test]
    public function rules_caps_applied_tags_at_five(): void
    {
        $rules = ChannelData::rules();

        $this->assertArrayHasKey('applied_tags', $rules);
        $this->assertContains('max:5', $rules['applied_tags']);
    }

    #[Test]
    public function rules_caps_available_tags_at_twenty(): void
    {
        $rules = ChannelData::rules();

        $this->assertArrayHasKey('available_tags', $rules);
        $this->assertContains('max:20', $rules['available_tags']);
    }

    #[Test]
    public function it_accepts_null_for_permission_overwrites_and_default_reaction_emoji(): void
    {
        $channel = ChannelData::from([...$this->minimalPayload(), 'permission_overwrites' => null]);
        $this->assertNull($channel->permissionOverwrites);

        $channel = ChannelData::from([...$this->minimalPayload(), 'default_reaction_emoji' => null]);
        $this->assertNull($channel->defaultReactionEmoji);
    }

    #[Test]
    public function it_maps_snake_case_keys_to_camel_case_properties_and_back(): void
    {
        $channel = ChannelData::from([
            'id' => '111111111111111111',
            'guild_id' => '829020506907869214',
            'rate_limit_per_user' => 30,
            'default_thread_rate_limit_per_user' => 60,
            'applied_tags' => ['1'],
        ]);

        $this->assertSame('829020506907869214', $channel->guildId);
        $this->assertSame(30, $channel->rateLimitPerUser);
        $this->assertSame(60, $channel->defaultThreadRateLimitPerUser);

        $array = $channel->toArray();

        $this->assertSame('829020506907869214', $array['guild_id']);
        $this->assertSame(60, $array['default_thread_rate_limit_per_user']);
        $this->assertArrayNotHasKey('guildId', $array);
    }

    #[Test]
    public function it_implements_the_channel_contract(): void
    {
        $this->assertInstanceOf(ChannelContract::class, ChannelData::from(['id' => '111111111111111111']));
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_more_than_five_applied_tags_supplied_under_the_wire_key(): void
    {
        $this->expectException(ValidationException::class);

        ChannelData::validate(['id' => '111111111111111111', 'applied_tags' => ['1', '2', '3', '4', '5', '6']]);
    }

    #[Test]
    #[Group('validation')]
    public function it_accepts_a_last_pin_timestamp_with_microseconds(): void
    {
        $validated = ChannelData::validate([
            ...$this->minimalPayload(),
            'last_pin_timestamp' => '2021-01-01T00:00:00.123000+00:00',
        ]);

        $this->assertSame('2021-01-01T00:00:00.123000+00:00', $validated['last_pin_timestamp']);
    }

    #[Test]
    #[Group('validation')]
    #[DataProvider('nonNullableOptionalFields')]
    public function it_rejects_null_for_fields_that_can_only_be_absent(string $field): void
    {
        $this->expectException(ValidationException::class);

        ChannelData::validate([...$this->minimalPayload(), $field => null]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonNullableOptionalFields(): array
    {
        return collect([
            'guild_id', 'position', 'nsfw', 'bitrate', 'user_limit', 'rate_limit_per_user', 'owner_id',
            'managed', 'video_quality_mode', 'message_count', 'member_count', 'default_auto_archive_duration',
            'permissions', 'flags', 'total_message_sent', 'default_forum_layout', 'default_thread_rate_limit_per_user',
        ])->mapWithKeys(fn (string $field) => [$field => [$field]])->all();
    }

    // ==================== notifiable ====================

    #[Test]
    public function its_key_is_its_id(): void
    {
        $channel = ChannelData::from($this->minimalPayload());

        $this->assertSame($channel->id, $channel->getKey());
    }

    #[Test]
    public function it_can_be_notified(): void
    {
        Notification::fake();

        $channel = ChannelData::from($this->minimalPayload());
        $notification = new class extends BaseNotification
        {
            public function via(object $notifiable): array
            {
                return ['mail'];
            }
        };

        $channel->notify($notification);

        Notification::assertSentTo($channel, $notification::class);
    }

    // ==================== readonly properties ====================

    #[Test]
    public function all_properties_are_readonly(): void
    {
        $channel = ChannelData::from($this->minimalPayload());
        $reflection = new ReflectionClass($channel);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== ChannelData::class) {
                continue;
            }

            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} should be readonly."
            );
        }
    }
}
