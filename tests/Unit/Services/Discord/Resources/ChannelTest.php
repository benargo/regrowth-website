<?php

namespace Tests\Unit\Services\Discord\Resources;

use App\Services\Discord\Enums\ChannelType;
use App\Services\Discord\Resources\Channel;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

class ChannelTest extends TestCase
{
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
    public function all_optional_fields_default_to_null(): void
    {
        $channel = Channel::from($this->minimalPayload());

        $this->assertNull($channel->guild_id);
        $this->assertNull($channel->position);
        $this->assertNull($channel->name);
        $this->assertNull($channel->topic);
        $this->assertNull($channel->nsfw);
        $this->assertNull($channel->last_message_id);
        $this->assertNull($channel->bitrate);
        $this->assertNull($channel->user_limit);
        $this->assertNull($channel->rate_limit_per_user);
        $this->assertNull($channel->owner_id);
        $this->assertNull($channel->application_id);
        $this->assertNull($channel->managed);
        $this->assertNull($channel->parent_id);
        $this->assertNull($channel->last_pin_timestamp);
        $this->assertNull($channel->rtc_region);
        $this->assertNull($channel->video_quality_mode);
        $this->assertNull($channel->message_count);
        $this->assertNull($channel->member_count);
        $this->assertNull($channel->default_auto_archive_duration);
        $this->assertNull($channel->permissions);
        $this->assertNull($channel->flags);
        $this->assertNull($channel->total_message_sent);
        $this->assertNull($channel->applied_tags);
        $this->assertNull($channel->default_sort_order);
        $this->assertNull($channel->default_forum_layout);
        $this->assertNull($channel->default_thread_rate_limit_per_user);
        $this->assertNull($channel->permission_overwrites);
        $this->assertNull($channel->recipients);
        $this->assertNull($channel->icon);
        $this->assertNull($channel->available_tags);
        $this->assertNull($channel->default_reaction_emoji);
        $this->assertNull($channel->thread_metadata);
        $this->assertNull($channel->member);
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
