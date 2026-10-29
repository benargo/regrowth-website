<?php

namespace Tests\Unit\Models;

use App\Http\Integrations\Discord\Enums\ChannelType;
use App\Models\DiscordChannel;
use App\Models\DiscordMessage;
use App\Models\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('discord-integration')]
class DiscordChannelTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return DiscordChannel::class;
    }

    #[Test]
    public function it_casts_type_position_and_nsfw(): void
    {
        $channel = DiscordChannel::factory()->create([
            'type' => 5,
            'position' => '3',
            'nsfw' => 1,
        ])->fresh();

        $this->assertSame(ChannelType::GuildAnnouncement, $channel->type);
        $this->assertSame(3, $channel->position);
        $this->assertTrue($channel->nsfw);
    }

    #[Test]
    public function it_has_a_parent_and_children(): void
    {
        $category = DiscordChannel::factory()->category()->create();
        $child = DiscordChannel::factory()->create(['parent_id' => $category->id]);

        $this->assertTrue($child->parent->is($category));
        $this->assertTrue($category->children->contains($child));
    }

    #[Test]
    public function it_has_events(): void
    {
        $channel = DiscordChannel::factory()->create();
        $event = Event::factory()->for($channel, 'channel')->create();

        $this->assertTrue($channel->events->contains($event));
    }

    #[Test]
    public function it_has_messages(): void
    {
        $channel = DiscordChannel::factory()->create();
        $message = DiscordMessage::factory()->for($channel, 'channel')->create();

        $this->assertTrue($channel->messages->contains($message));
    }

    #[Test]
    public function threads_scope_returns_only_threads(): void
    {
        DiscordChannel::factory()->create();
        $thread = DiscordChannel::factory()->thread()->create();
        DiscordChannel::factory()->stub()->create();

        $this->assertSame([$thread->id], DiscordChannel::threads()->pluck('id')->all());
    }

    #[Test]
    public function without_threads_scope_includes_stubs_and_excludes_threads(): void
    {
        $text = DiscordChannel::factory()->create();
        DiscordChannel::factory()->thread()->create();
        $stub = DiscordChannel::factory()->stub()->create();

        $this->assertEqualsCanonicalizing(
            [$text->id, $stub->id],
            DiscordChannel::withoutThreads()->pluck('id')->all(),
        );
    }

    #[Test]
    public function stubs_scope_returns_only_null_type_rows(): void
    {
        DiscordChannel::factory()->create();
        DiscordChannel::factory()->thread()->create();
        $stub = DiscordChannel::factory()->stub()->create();

        $this->assertSame([$stub->id], DiscordChannel::stubs()->pluck('id')->all());
    }

    #[Test]
    public function it_is_excluded_from_default_queries_when_trashed(): void
    {
        $channel = DiscordChannel::factory()->trashed()->create();

        $this->assertNull(DiscordChannel::find($channel->id));
        $this->assertNotNull(DiscordChannel::withTrashed()->find($channel->id));
    }
}
