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

    // ==================== content tags ====================

    #[Test]
    public function it_limits_the_directory_to_tags_in_the_content(): void
    {
        $channel = DiscordChannel::factory()->create();
        DiscordChannel::factory()->create();
        $role = DiscordRole::factory()->create();
        DiscordRole::factory()->create();
        $emoji = DiscordEmoji::factory()->animated()->create(['name' => 'wave']);
        DiscordEmoji::factory()->create(['name' => 'other']);

        $content = "See <#{$channel->id}> for <@&{$role->id}> <a:wave:{$emoji->id}>";

        $directory = BuildDiscordMentionDirectory::run($content);

        $this->assertSame([$channel->id], array_column($directory->channels, 'id'));
        $this->assertSame([$role->id], array_column($directory->roles, 'id'));
        $this->assertSame([$emoji->id], array_column($directory->emoji, 'id'));
    }

    #[Test]
    public function it_matches_static_emoji_tags_in_the_content(): void
    {
        $emoji = DiscordEmoji::factory()->create(['name' => 'smile']);

        $directory = BuildDiscordMentionDirectory::run("Hello <:smile:{$emoji->id}>");

        $this->assertSame([$emoji->id], array_column($directory->emoji, 'id'));
    }

    #[Test]
    public function it_does_not_treat_user_mentions_as_channel_or_role_tags(): void
    {
        $channel = DiscordChannel::factory()->create();
        $role = DiscordRole::factory()->create();

        $directory = BuildDiscordMentionDirectory::run("Ask <@{$channel->id}> or <@{$role->id}>");

        $this->assertSame([], $directory->channels);
        $this->assertSame([], $directory->roles);
    }

    // ==================== links ====================

    #[Test]
    public function it_links_a_channel_to_discord(): void
    {
        config(['services.discord.server_id' => '9999999999999999999']);
        $channel = DiscordChannel::factory()->create();

        $found = collect(BuildDiscordMentionDirectory::run()->channels)->firstWhere('id', $channel->id);

        $this->assertSame("https://discord.com/channels/9999999999999999999/{$channel->id}", $found->url);
    }

    #[Test]
    public function it_links_a_thread_through_its_parent_channel(): void
    {
        config(['services.discord.server_id' => '9999999999999999999']);
        $text = DiscordChannel::factory()->create();
        $thread = DiscordChannel::factory()->thread()->create(['parent_id' => $text->id]);

        $found = collect(BuildDiscordMentionDirectory::run()->channels)->firstWhere('id', $thread->id);

        $this->assertSame("https://discord.com/channels/9999999999999999999/{$text->id}/{$thread->id}", $found->url);
    }

    // ==================== deleted categories ====================

    #[Test]
    public function it_names_the_category_of_a_channel_whose_category_was_deleted(): void
    {
        $category = DiscordChannel::factory()->category()->create(['name' => 'Archive']);
        $channel = DiscordChannel::factory()->create(['parent_id' => $category->id]);
        $category->delete();

        $found = collect(BuildDiscordMentionDirectory::run()->channels)->firstWhere('id', $channel->id);

        $this->assertSame('Archive', $found->category);
    }

    #[Test]
    public function it_names_a_threads_category_when_its_parent_and_category_were_deleted(): void
    {
        $category = DiscordChannel::factory()->category()->create(['name' => 'Archive']);
        $text = DiscordChannel::factory()->create(['parent_id' => $category->id]);
        $thread = DiscordChannel::factory()->thread()->create(['parent_id' => $text->id]);
        $text->delete();
        $category->delete();

        $found = collect(BuildDiscordMentionDirectory::run()->channels)->firstWhere('id', $thread->id);

        $this->assertSame('Archive', $found->category);
    }

    // ==================== sort order ====================

    #[Test]
    #[Group('contract')]
    public function it_sorts_channels_by_category_then_position_then_name(): void
    {
        $zulu = DiscordChannel::factory()->category()->create(['name' => 'zulu', 'position' => 0]);
        $alpha = DiscordChannel::factory()->category()->create(['name' => 'Alpha', 'position' => 1]);
        $zuluFirst = DiscordChannel::factory()->create(['name' => 'first', 'parent_id' => $zulu->id, 'position' => 1]);
        $alphaLate = DiscordChannel::factory()->create(['name' => 'late', 'parent_id' => $alpha->id, 'position' => 5]);
        $alphaBravo = DiscordChannel::factory()->create(['name' => 'Bravo', 'parent_id' => $alpha->id, 'position' => 2]);
        $alphaAble = DiscordChannel::factory()->create(['name' => 'able', 'parent_id' => $alpha->id, 'position' => 2]);

        $directory = BuildDiscordMentionDirectory::run();

        $this->assertSame(
            [$zulu->id, $alpha->id, $alphaAble->id, $alphaBravo->id, $alphaLate->id, $zuluFirst->id],
            array_column($directory->channels, 'id'),
        );
    }

    #[Test]
    #[Group('contract')]
    public function it_sorts_roles_by_position_descending_and_emoji_by_name(): void
    {
        $low = DiscordRole::factory()->create(['position' => 100]);
        $high = DiscordRole::factory()->create(['position' => 300]);
        $middle = DiscordRole::factory()->create(['position' => 200]);
        $wave = DiscordEmoji::factory()->create(['name' => 'wave']);
        $angry = DiscordEmoji::factory()->create(['name' => 'angry']);

        $directory = BuildDiscordMentionDirectory::run();

        $this->assertSame([$high->id, $middle->id, $low->id], array_column($directory->roles, 'id'));
        $this->assertSame([$angry->id, $wave->id], array_column($directory->emoji, 'id'));
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
