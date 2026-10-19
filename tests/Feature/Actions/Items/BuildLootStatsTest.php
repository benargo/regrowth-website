<?php

namespace Tests\Feature\Actions\Items;

use App\Actions\Items\BuildLootStats;
use App\Data\Items\LootStatsData;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\GameVersion;
use App\Models\Item;
use App\Models\ItemPriority;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class BuildLootStatsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    public function it_counts_only_the_given_versions_records(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $items = Item::factory()->count(2)->create(['game_version_id' => $gameVersion->id]);
        ItemPriority::factory()->for($items[0])->weight(50)->create();
        ItemPriority::factory()->for($items[0])->weight(50)->create();
        ItemPriority::factory()->for($items[1])->weight(30)->create();
        $commenter = User::factory()->create();
        $comment = Comment::factory()->for($items[0], 'commentable')->create(['user_id' => $commenter->id]);
        Comment::factory()->for($items[1], 'commentable')->create(['user_id' => $commenter->id]);
        Comment::factory()->for($items[1], 'commentable')->create();
        CommentReaction::factory()->forComment($comment)->create();

        $otherItem = Item::factory()->create(['game_version_id' => GameVersion::factory()->create()->id]);
        $unversionedItem = Item::factory()->create();
        ItemPriority::factory()->for($otherItem)->weight(10)->create();
        CommentReaction::factory()->forComment(Comment::factory()->for($otherItem, 'commentable')->create())->create();
        Comment::factory()->for($unversionedItem, 'commentable')->create();

        $stats = BuildLootStats::run($gameVersion);

        $this->assertEquals(new LootStatsData(
            itemsCount: 2,
            priorityRowsCount: 2,
            commentsCount: 3,
            commentersCount: 2,
            reactionsCount: 1,
        ), $stats);
    }

    #[Test]
    public function it_returns_zeros_without_a_game_version(): void
    {
        Comment::factory()->for(Item::factory()->create(), 'commentable')->create();

        $stats = BuildLootStats::run(null);

        $this->assertEquals(LootStatsData::blank(), $stats);
    }
}
