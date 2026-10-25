<?php

namespace Tests\Feature\Actions\Comments;

use App\Actions\Comments\ListLatestDiscussions;
use App\Data\Navigation\LatestDiscussionData;
use App\Enums\ItemQuality;
use App\Models\Boss;
use App\Models\Comment;
use App\Models\GameVersion;
use App\Models\Item;
use App\Support\MediaLibrary\UnknownBlizzardIcon;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('comments')]
class ListLatestDiscussionsTest extends TestCase
{
    use RefreshDatabase;

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();

        $this->gameVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);
    }

    #[Test]
    public function it_lists_the_three_newest_root_comments_on_the_versions_items(): void
    {
        $item = Item::factory()->withName()->create(['game_version_id' => $this->gameVersion->id]);
        $roots = collect([1, 2, 3, 4])->mapWithKeys(fn (int $hours): array => [
            $hours => $this->commentOn($item, now()->subHours($hours)),
        ]);
        Comment::factory()->replyTo($roots[4])->create(['created_at' => now()->subMinute()]);
        $otherVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2023-01-01')]);
        $this->commentOn(Item::factory()->withName()->create(['game_version_id' => $otherVersion->id]), now()->subMinutes(2));
        $this->commentOn(Item::factory()->withName()->create(), now()->subMinutes(3));

        $discussions = ListLatestDiscussions::run($this->gameVersion);

        $this->assertSame(
            [$roots[1]->id, $roots[2]->id, $roots[3]->id],
            $discussions->map(fn (LatestDiscussionData $discussion): int => $discussion->id)->all(),
        );
    }

    #[Test]
    public function it_describes_each_discussion(): void
    {
        $boss = Boss::factory()->create(['name' => 'Gruul the Dragonkiller']);
        $item = Item::factory()->fromBoss($boss)->withName('Dragonspine Trophy')->withQuality(ItemQuality::EPIC)->create([
            'game_version_id' => $this->gameVersion->id,
        ]);
        $root = $this->commentOn($item, now()->subHours(2));
        Comment::factory()->replyTo($root)->count(2)->create();

        $discussion = ListLatestDiscussions::run($this->gameVersion)->sole();

        $this->assertSame('Dragonspine Trophy', $discussion->itemName);
        $this->assertSame('text-quality-epic', $discussion->itemQualityClass);
        $this->assertSame('Gruul the Dragonkiller', $discussion->bossName);
        $this->assertSame('2 hours ago', $discussion->postedAgo);
        $this->assertSame(2, $discussion->repliesCount);
        $this->assertSame(route('loot.items.show', ['item' => $item, 'slug' => $item->slug]), $discussion->url);
    }

    #[Test]
    public function it_includes_the_items_first_blizzard_icon(): void
    {
        Storage::fake('s3');
        $item = Item::factory()->withName()->create(['game_version_id' => $this->gameVersion->id]);
        $item->addMediaFromString('BINARY')
            ->usingFileName('inv_sword_04.jpg')
            ->toMediaCollection('blizzard_icons');
        $this->commentOn($item, now()->subHour());

        $discussion = ListLatestDiscussions::run($this->gameVersion)->sole();

        $this->assertSame($item->getFirstMediaUrl('blizzard_icons'), $discussion->itemIcon);
    }

    #[Test]
    public function it_sets_the_questionmark_icon_when_the_item_has_no_blizzard_icon(): void
    {
        Storage::fake('s3');
        $this->commentOn(Item::factory()->withName()->create(['game_version_id' => $this->gameVersion->id]), now()->subHour());

        $this->assertSame(app(UnknownBlizzardIcon::class)->url(), ListLatestDiscussions::run($this->gameVersion)->sole()->itemIcon);
    }

    #[Test]
    public function it_skips_comments_on_nameless_items(): void
    {
        $named = $this->commentOn(Item::factory()->withName()->create(['game_version_id' => $this->gameVersion->id]), now()->subHours(2));
        $this->commentOn(Item::factory()->create(['name' => null, 'game_version_id' => $this->gameVersion->id]), now()->subHour());

        $this->assertSame(
            [$named->id],
            ListLatestDiscussions::run($this->gameVersion)->map(fn (LatestDiscussionData $discussion): int => $discussion->id)->all(),
        );
    }

    #[Test]
    public function it_returns_nothing_without_any_game_version(): void
    {
        $this->commentOn(Item::factory()->withName()->create(['game_version_id' => $this->gameVersion->id]), now()->subHour());

        $this->assertTrue(ListLatestDiscussions::run(null)->isEmpty());
    }

    private function commentOn(Item $item, Carbon $createdAt): Comment
    {
        return Comment::factory()->for($item, 'commentable')->create(['created_at' => $createdAt]);
    }
}
