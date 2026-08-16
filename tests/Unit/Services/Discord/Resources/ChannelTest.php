<?php

namespace Tests\Unit\Services\Discord\Resources;

use App\Services\Discord\Enums\ChannelType;
use App\Services\Discord\Resources\Channel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

#[Group('discord-integration')]
class ChannelTest extends TestCase
{
    #[Test]
    public function channel_can_be_constructed_directly(): void
    {
        $channel = new Channel(
            id: '123456789012345678',
            type: Optional::create(),
            guild_id: Optional::create(),
            position: Optional::create(),
            name: Optional::create(),
            topic: Optional::create(),
            nsfw: Optional::create(),
            last_message_id: Optional::create(),
            bitrate: Optional::create(),
            user_limit: Optional::create(),
            rate_limit_per_user: Optional::create(),
            owner_id: Optional::create(),
            application_id: Optional::create(),
            managed: Optional::create(),
            parent_id: Optional::create(),
            last_pin_timestamp: Optional::create(),
            rtc_region: Optional::create(),
            video_quality_mode: Optional::create(),
            message_count: Optional::create(),
            member_count: Optional::create(),
            default_auto_archive_duration: Optional::create(),
            permissions: Optional::create(),
            flags: Optional::create(),
            total_message_sent: Optional::create(),
            applied_tags: Optional::create(),
            default_sort_order: Optional::create(),
            default_forum_layout: Optional::create(),
            default_thread_rate_limit_per_user: Optional::create(),
            permission_overwrites: Optional::create(),
            recipients: Optional::create(),
            icon: Optional::create(),
            available_tags: Optional::create(),
            default_reaction_emoji: Optional::create(),
            thread_metadata: Optional::create(),
            member: Optional::create(),
        );

        $this->assertSame('123456789012345678', $channel->id);
        $this->assertInstanceOf(Optional::class, $channel->type);
        $this->assertInstanceOf(Optional::class, $channel->guild_id);
        $this->assertInstanceOf(Optional::class, $channel->position);
        $this->assertInstanceOf(Optional::class, $channel->name);
        $this->assertInstanceOf(Optional::class, $channel->topic);
        $this->assertInstanceOf(Optional::class, $channel->nsfw);
        $this->assertInstanceOf(Optional::class, $channel->last_message_id);
        $this->assertInstanceOf(Optional::class, $channel->bitrate);
        $this->assertInstanceOf(Optional::class, $channel->user_limit);
        $this->assertInstanceOf(Optional::class, $channel->rate_limit_per_user);
        $this->assertInstanceOf(Optional::class, $channel->owner_id);
        $this->assertInstanceOf(Optional::class, $channel->application_id);
        $this->assertInstanceOf(Optional::class, $channel->managed);
        $this->assertInstanceOf(Optional::class, $channel->parent_id);
        $this->assertInstanceOf(Optional::class, $channel->last_pin_timestamp);
        $this->assertInstanceOf(Optional::class, $channel->rtc_region);
        $this->assertInstanceOf(Optional::class, $channel->video_quality_mode);
        $this->assertInstanceOf(Optional::class, $channel->message_count);
        $this->assertInstanceOf(Optional::class, $channel->member_count);
        $this->assertInstanceOf(Optional::class, $channel->default_auto_archive_duration);
        $this->assertInstanceOf(Optional::class, $channel->permissions);
        $this->assertInstanceOf(Optional::class, $channel->flags);
        $this->assertInstanceOf(Optional::class, $channel->total_message_sent);
        $this->assertInstanceOf(Optional::class, $channel->applied_tags);
        $this->assertInstanceOf(Optional::class, $channel->default_sort_order);
        $this->assertInstanceOf(Optional::class, $channel->default_forum_layout);
        $this->assertInstanceOf(Optional::class, $channel->default_thread_rate_limit_per_user);
        $this->assertInstanceOf(Optional::class, $channel->permission_overwrites);
        $this->assertInstanceOf(Optional::class, $channel->recipients);
        $this->assertInstanceOf(Optional::class, $channel->icon);
        $this->assertInstanceOf(Optional::class, $channel->available_tags);
        $this->assertInstanceOf(Optional::class, $channel->default_reaction_emoji);
        $this->assertInstanceOf(Optional::class, $channel->thread_metadata);
        $this->assertInstanceOf(Optional::class, $channel->member);
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalPayload(): array
    {
        return [
            'id' => '123456789012345678',
            'type' => ChannelType::GUILD_TEXT->value,
        ];
    }

    #[Test]
    public function it_constructs_from_minimal_payload(): void
    {
        $channel = Channel::from($this->minimalPayload());

        $this->assertSame('123456789012345678', $channel->id);
        $this->assertSame(ChannelType::GUILD_TEXT, $channel->type);
    }

    #[Test]
    public function all_optional_fields_default_to_optional(): void
    {
        $channel = Channel::from($this->minimalPayload());

        $this->assertInstanceOf(Optional::class, $channel->guild_id);
        $this->assertInstanceOf(Optional::class, $channel->position);
        $this->assertInstanceOf(Optional::class, $channel->name);
        $this->assertInstanceOf(Optional::class, $channel->topic);
        $this->assertInstanceOf(Optional::class, $channel->nsfw);
        $this->assertInstanceOf(Optional::class, $channel->last_message_id);
        $this->assertInstanceOf(Optional::class, $channel->bitrate);
        $this->assertInstanceOf(Optional::class, $channel->user_limit);
        $this->assertInstanceOf(Optional::class, $channel->rate_limit_per_user);
        $this->assertInstanceOf(Optional::class, $channel->owner_id);
        $this->assertInstanceOf(Optional::class, $channel->application_id);
        $this->assertInstanceOf(Optional::class, $channel->managed);
        $this->assertInstanceOf(Optional::class, $channel->parent_id);
        $this->assertInstanceOf(Optional::class, $channel->last_pin_timestamp);
        $this->assertInstanceOf(Optional::class, $channel->rtc_region);
        $this->assertInstanceOf(Optional::class, $channel->video_quality_mode);
        $this->assertInstanceOf(Optional::class, $channel->message_count);
        $this->assertInstanceOf(Optional::class, $channel->member_count);
        $this->assertInstanceOf(Optional::class, $channel->default_auto_archive_duration);
        $this->assertInstanceOf(Optional::class, $channel->permissions);
        $this->assertInstanceOf(Optional::class, $channel->flags);
        $this->assertInstanceOf(Optional::class, $channel->total_message_sent);
        $this->assertInstanceOf(Optional::class, $channel->applied_tags);
        $this->assertInstanceOf(Optional::class, $channel->default_sort_order);
        $this->assertInstanceOf(Optional::class, $channel->default_forum_layout);
        $this->assertInstanceOf(Optional::class, $channel->default_thread_rate_limit_per_user);
        $this->assertInstanceOf(Optional::class, $channel->permission_overwrites);
        $this->assertInstanceOf(Optional::class, $channel->recipients);
        $this->assertInstanceOf(Optional::class, $channel->icon);
        $this->assertInstanceOf(Optional::class, $channel->available_tags);
        $this->assertInstanceOf(Optional::class, $channel->default_reaction_emoji);
        $this->assertInstanceOf(Optional::class, $channel->thread_metadata);
        $this->assertInstanceOf(Optional::class, $channel->member);
    }

    #[Test]
    public function it_hydrates_the_channel_type_enum(): void
    {
        foreach (ChannelType::cases() as $case) {
            $channel = Channel::from(['id' => '1', 'type' => $case->value]);
            $this->assertSame($case, $channel->type);
        }
    }

    #[Test]
    public function it_stores_all_scalar_optional_fields(): void
    {
        $channel = Channel::from([
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

        $this->assertSame('111', $channel->guild_id);
        $this->assertSame(3, $channel->position);
        $this->assertSame('general', $channel->name);
        $this->assertSame('Welcome!', $channel->topic);
        $this->assertFalse($channel->nsfw);
        $this->assertSame('999', $channel->last_message_id);
        $this->assertSame(64000, $channel->bitrate);
        $this->assertSame(10, $channel->user_limit);
        $this->assertSame(30, $channel->rate_limit_per_user);
        $this->assertSame('222', $channel->owner_id);
        $this->assertSame('333', $channel->application_id);
        $this->assertTrue($channel->managed);
        $this->assertSame('444', $channel->parent_id);
        $this->assertSame('2024-01-01T00:00:00Z', $channel->last_pin_timestamp);
        $this->assertSame('us-east', $channel->rtc_region);
        $this->assertSame(1, $channel->video_quality_mode);
        $this->assertSame(42, $channel->message_count);
        $this->assertSame(5, $channel->member_count);
        $this->assertSame(1440, $channel->default_auto_archive_duration);
        $this->assertSame('8', $channel->permissions);
        $this->assertSame(0, $channel->flags);
        $this->assertSame(100, $channel->total_message_sent);
        $this->assertSame(0, $channel->default_sort_order);
        $this->assertSame(1, $channel->default_forum_layout);
        $this->assertSame(60, $channel->default_thread_rate_limit_per_user);
        $this->assertSame('abc123hash', $channel->icon);
    }

    #[Test]
    public function it_accepts_null_for_default_reaction_emoji(): void
    {
        $channel = Channel::from([...$this->minimalPayload(), 'default_reaction_emoji' => null]);
        $this->assertNull($channel->default_reaction_emoji);
    }

    #[Test]
    public function it_stores_null_for_nullable_optional_fields(): void
    {
        $channel = Channel::from([...$this->minimalPayload(), 'topic' => null]);
        $this->assertNull($channel->topic);

        $channel = Channel::from([...$this->minimalPayload(), 'last_message_id' => null]);
        $this->assertNull($channel->last_message_id);

        $channel = Channel::from([...$this->minimalPayload(), 'last_pin_timestamp' => null]);
        $this->assertNull($channel->last_pin_timestamp);

        $channel = Channel::from([...$this->minimalPayload(), 'rtc_region' => null]);
        $this->assertNull($channel->rtc_region);

        $channel = Channel::from([...$this->minimalPayload(), 'parent_id' => null]);
        $this->assertNull($channel->parent_id);

        $channel = Channel::from([...$this->minimalPayload(), 'default_sort_order' => null]);
        $this->assertNull($channel->default_sort_order);

        $channel = Channel::from([...$this->minimalPayload(), 'icon' => null]);
        $this->assertNull($channel->icon);
    }

    #[Test]
    public function it_stores_array_fields(): void
    {
        $tags = [['id' => '1', 'name' => 'bug'], ['id' => '2', 'name' => 'help']];
        $overwrites = [['id' => '10', 'type' => 0, 'allow' => '0', 'deny' => '8']];

        $channel = Channel::from([
            ...$this->minimalPayload(),
            'applied_tags' => ['1', '2'],
            'available_tags' => $tags,
            'permission_overwrites' => $overwrites,
            'recipients' => [['id' => '50', 'username' => 'Thrall']],
            'default_reaction_emoji' => ['emoji_id' => '77', 'emoji_name' => null],
            'thread_metadata' => ['archived' => false, 'auto_archive_duration' => 60],
            'member' => ['user_id' => '99', 'join_timestamp' => '2024-01-01T00:00:00Z'],
        ]);

        $this->assertSame(['1', '2'], $channel->applied_tags);
        $this->assertSame($tags, $channel->available_tags);
        $this->assertSame($overwrites, $channel->permission_overwrites);
        $this->assertSame([['id' => '50', 'username' => 'Thrall']], $channel->recipients);
        $this->assertSame(['emoji_id' => '77', 'emoji_name' => null], $channel->default_reaction_emoji);
        $this->assertSame(['archived' => false, 'auto_archive_duration' => 60], $channel->thread_metadata);
        $this->assertSame(['user_id' => '99', 'join_timestamp' => '2024-01-01T00:00:00Z'], $channel->member);
    }

    #[Test]
    public function rules_caps_applied_tags_at_five(): void
    {
        $rules = Channel::rules();

        $this->assertArrayHasKey('applied_tags', $rules);
        $this->assertContains('max:5', $rules['applied_tags']);
    }

    #[Test]
    public function rules_caps_available_tags_at_twenty(): void
    {
        $rules = Channel::rules();

        $this->assertArrayHasKey('available_tags', $rules);
        $this->assertContains('max:20', $rules['available_tags']);
    }

    #[Test]
    public function it_accepts_null_for_permission_overwrites_and_default_reaction_emoji(): void
    {
        $channel = Channel::from([...$this->minimalPayload(), 'permission_overwrites' => null]);
        $this->assertNull($channel->permission_overwrites);

        $channel = Channel::from([...$this->minimalPayload(), 'default_reaction_emoji' => null]);
        $this->assertNull($channel->default_reaction_emoji);
    }

    #[Test]
    public function all_properties_are_readonly(): void
    {
        $channel = Channel::from($this->minimalPayload());
        $reflection = new ReflectionClass($channel);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== Channel::class) {
                continue;
            }

            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} should be readonly."
            );
        }
    }
}
