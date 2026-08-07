<?php

namespace Tests\Feature\Comments;

use App\Models\Boss;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\Item;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('comments')]
#[Group('loot')]
class ReactionTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockItemService();
    }

    // ==========================================
    // Model validation tests
    // ==========================================

    #[Group('error-handling')]
    #[Test]
    public function model_prevents_user_from_reacting_to_own_comment_directly(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $user->id,
        ]);

        $this->expectException(ValidationException::class);

        CommentReaction::create([
            'comment_id' => $comment->id,
            'user_id' => $user->id,
        ]);
    }

    #[Group('error-handling')]
    #[Test]
    public function user_cannot_react_to_same_comment_twice(): void
    {
        $item = $this->createItem();
        $commentAuthor = User::factory()->raider()->create();
        $reactingUser = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);

        CommentReaction::factory()->create([
            'comment_id' => $comment->id,
            'user_id' => $reactingUser->id,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        CommentReaction::factory()->create([
            'comment_id' => $comment->id,
            'user_id' => $reactingUser->id,
        ]);
    }

    private function createItem(): Item
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        return Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);
    }
}
