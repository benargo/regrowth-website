<?php

namespace Tests\Feature\Actions\Discord;

use App\Actions\Discord\BuildDiscordMentionDirectory;
use App\Models\DiscordChannel;
use App\Models\DiscordEmoji;
use App\Models\DiscordRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
#[Group('discord-integration')]
class BuildDiscordMentionDirectoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_channels_roles_and_emoji(): void
    {
        $category = DiscordChannel::factory()->category()->create(['name' => 'Raiding']);
        $channel = DiscordChannel::factory()->create(['name' => 'tactics', 'parent_id' => $category->id]);
        $role = DiscordRole::factory()->create(['name' => 'Raider']);
        $emoji = DiscordEmoji::factory()->animated()->create(['name' => 'wave', 'available' => false]);

        $directory = BuildDiscordMentionDirectory::run();

        $found = collect($directory->channels)->firstWhere('id', $channel->id);
        $this->assertSame('tactics', $found->name);
        $this->assertSame('Raiding', $found->category);
        $this->assertFalse($found->isCategory);
        $this->assertFalse($found->isDeleted);

        $this->assertCount(1, $directory->roles);
        $this->assertSame($role->id, $directory->roles[0]->id);
        $this->assertSame('Raider', $directory->roles[0]->name);

        $this->assertCount(1, $directory->emoji);
        $this->assertSame($emoji->id, $directory->emoji[0]->id);
        $this->assertSame('wave', $directory->emoji[0]->name);
        $this->assertTrue($directory->emoji[0]->animated);
        $this->assertFalse($directory->emoji[0]->available);
        $this->assertSame($emoji->url, $directory->emoji[0]->url);
    }

    #[Test]
    public function it_names_a_threads_category_from_its_parent_channel(): void
    {
        $category = DiscordChannel::factory()->category()->create(['name' => 'Raiding']);
        $text = DiscordChannel::factory()->create(['name' => 'tactics', 'parent_id' => $category->id]);
        $thread = DiscordChannel::factory()->thread()->create(['name' => 'boss-one', 'parent_id' => $text->id]);

        $found = collect(BuildDiscordMentionDirectory::run()->channels)->firstWhere('id', $thread->id);

        $this->assertSame('Raiding', $found->category);
    }

    #[Test]
    public function it_marks_categories_and_deleted_channels(): void
    {
        $category = DiscordChannel::factory()->category()->create(['name' => 'Raiding']);
        $deleted = DiscordChannel::factory()->create(['name' => 'old', 'parent_id' => $category->id]);
        $deleted->delete();

        $channels = collect(BuildDiscordMentionDirectory::run()->channels);

        $this->assertTrue($channels->firstWhere('id', $category->id)->isCategory);
        $this->assertNull($channels->firstWhere('id', $category->id)->category);
        $this->assertTrue($channels->firstWhere('id', $deleted->id)->isDeleted);
        $this->assertFalse($channels->firstWhere('id', $category->id)->isDeleted);
    }

    #[Test]
    public function it_leaves_out_stub_channels(): void
    {
        DiscordChannel::factory()->stub()->create();
        $channel = DiscordChannel::factory()->create();

        $channels = BuildDiscordMentionDirectory::run()->channels;

        $this->assertCount(1, $channels);
        $this->assertSame($channel->id, $channels[0]->id);
    }

    #[Test]
    public function it_limits_the_directory_to_tags_in_the_content(): void
    {
        [$channel, $otherChannel] = DiscordChannel::factory()->count(2)->create();
        [$role, $otherRole] = DiscordRole::factory()->count(2)->create();
        $emoji = DiscordEmoji::factory()->animated()->create(['name' => 'wave']);
        DiscordEmoji::factory()->create(['name' => 'other']);

        $content = "See <#{$channel->id}> for <@&{$role->id}> <a:wave:{$emoji->id}>";

        $directory = BuildDiscordMentionDirectory::run($content);

        $this->assertSame([$channel->id], array_column($directory->channels, 'id'));
        $this->assertSame([$role->id], array_column($directory->roles, 'id'));
        $this->assertSame([$emoji->id], array_column($directory->emoji, 'id'));
        $this->assertNotSame($otherChannel->id, $directory->channels[0]->id);
        $this->assertNotSame($otherRole->id, $directory->roles[0]->id);
    }

    #[Test]
    public function it_returns_an_empty_directory_for_content_without_tags(): void
    {
        DiscordChannel::factory()->create();
        DiscordRole::factory()->create();
        DiscordEmoji::factory()->create();

        $directory = BuildDiscordMentionDirectory::run('Plain text');

        $this->assertSame([], $directory->channels);
        $this->assertSame([], $directory->roles);
        $this->assertSame([], $directory->emoji);
    }
}
