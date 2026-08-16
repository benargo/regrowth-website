<?php

namespace Tests\Feature\Api;

use App\Events\Broadcasts\CommentReactionChanged;
use App\Models\Boss;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\DiscordRole;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('comments')]
#[Group('loot')]
class CommentReactionControllerTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockItemService();

        $reactToComments = Permission::firstOrCreate(['name' => 'react-to-comments', 'guard_name' => 'web']);

        DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 6, 'is_visible' => true])
            ->givePermissionTo($reactToComments);
        DiscordRole::firstOrCreate(['id' => '1265247017215594496'], ['name' => 'Raider', 'position' => 4, 'is_visible' => true])
            ->givePermissionTo($reactToComments);
        DiscordRole::firstOrCreate(['id' => '829022020301094922'], ['name' => 'Member', 'position' => 3, 'is_visible' => true])
            ->givePermissionTo($reactToComments);
    }

    // ==================== store — authorization ====================

    #[Group('authorization')]
    #[Test]
    public function unauthenticated_users_cannot_react_to_comments(): void
    {
        $comment = $this->createComment(User::factory()->raider()->create());

        $response = $this->postJson(route('api.comments.reactions.store'), ['comment_id' => $comment->id]);

        $response->assertUnauthorized();
        $this->assertDatabaseCount('pivot_comments_reactions', 0);
    }

    #[Test]
    public function raiders_can_react_to_other_users_comments(): void
    {
        $comment = $this->createComment(User::factory()->raider()->create());
        $reactingUser = User::factory()->raider()->create();

        $response = $this->actingAs($reactingUser)
            ->postJson(route('api.comments.reactions.store'), ['comment_id' => $comment->id]);

        $response->assertCreated();
        $this->assertDatabaseHas('pivot_comments_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $reactingUser->id,
        ]);
    }

    #[Test]
    public function member_users_can_react_to_comments(): void
    {
        $comment = $this->createComment(User::factory()->raider()->create());
        $memberUser = User::factory()->member()->create();

        $response = $this->actingAs($memberUser)
            ->postJson(route('api.comments.reactions.store'), ['comment_id' => $comment->id]);

        $response->assertCreated();
        $this->assertDatabaseHas('pivot_comments_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $memberUser->id,
        ]);
    }

    #[Test]
    public function officer_users_can_react_to_comments(): void
    {
        $comment = $this->createComment(User::factory()->raider()->create());
        $officerUser = User::factory()->officer()->create();

        $response = $this->actingAs($officerUser)
            ->postJson(route('api.comments.reactions.store'), ['comment_id' => $comment->id]);

        $response->assertCreated();
        $this->assertDatabaseHas('pivot_comments_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $officerUser->id,
        ]);
    }

    #[Group('authorization')]
    #[Test]
    public function users_cannot_react_to_their_own_comments(): void
    {
        $user = User::factory()->raider()->create();
        $comment = $this->createComment($user);

        $response = $this->actingAs($user)
            ->postJson(route('api.comments.reactions.store'), ['comment_id' => $comment->id]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('pivot_comments_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $user->id,
        ]);
    }

    #[Group('authorization')]
    #[Test]
    public function guest_users_cannot_react_to_comments(): void
    {
        $comment = $this->createComment(User::factory()->raider()->create());
        $guestUser = User::factory()->guest()->create();

        $response = $this->actingAs($guestUser)
            ->postJson(route('api.comments.reactions.store'), ['comment_id' => $comment->id]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('pivot_comments_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $guestUser->id,
        ]);
    }

    // ==================== store — validation ====================

    #[Group('validation')]
    #[Test]
    public function reacting_fails_without_a_comment_id(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->postJson(route('api.comments.reactions.store'), []);

        $response->assertForbidden();
        $this->assertDatabaseCount('pivot_comments_reactions', 0);
    }

    #[Group('validation')]
    #[Test]
    public function reacting_fails_when_the_comment_does_not_exist(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.comments.reactions.store'), ['comment_id' => 999999]);

        $response->assertForbidden();
        $this->assertDatabaseCount('pivot_comments_reactions', 0);
    }

    // ==================== store — behaviour ====================

    #[Test]
    public function store_returns_the_created_reaction_as_a_resource(): void
    {
        $comment = $this->createComment(User::factory()->raider()->create());
        $reactingUser = User::factory()->raider()->create();

        $response = $this->actingAs($reactingUser)
            ->postJson(route('api.comments.reactions.store'), ['comment_id' => $comment->id]);

        $response->assertCreated();
        $response->assertJsonStructure(['data' => ['id', 'comment_id', 'user', 'created_at']]);
        $response->assertJsonPath('data.comment_id', $comment->id);
        $response->assertJsonPath('data.user.id', $reactingUser->id);
    }

    // ==================== destroy ====================

    #[Group('authorization')]
    #[Test]
    public function unauthenticated_users_cannot_delete_reactions(): void
    {
        $comment = $this->createComment(User::factory()->raider()->create());
        $reaction = CommentReaction::factory()
            ->forComment($comment)
            ->byUser(User::factory()->raider()->create())
            ->create();

        $response = $this->deleteJson(route('api.comments.reactions.destroy', $reaction));

        $response->assertUnauthorized();
        $this->assertDatabaseHas('pivot_comments_reactions', ['id' => $reaction->id]);
    }

    #[Test]
    public function users_can_delete_their_own_reaction(): void
    {
        $comment = $this->createComment(User::factory()->raider()->create());
        $reactor = User::factory()->raider()->create();
        $reaction = CommentReaction::factory()->forComment($comment)->byUser($reactor)->create();

        $response = $this->actingAs($reactor)
            ->deleteJson(route('api.comments.reactions.destroy', $reaction));

        $response->assertNoContent();
        $this->assertDatabaseMissing('pivot_comments_reactions', ['id' => $reaction->id]);
    }

    #[Group('authorization')]
    #[Test]
    public function users_cannot_delete_another_users_reaction(): void
    {
        $comment = $this->createComment(User::factory()->raider()->create());
        $reactor = User::factory()->raider()->create();
        $interloper = User::factory()->raider()->create();
        $reaction = CommentReaction::factory()->forComment($comment)->byUser($reactor)->create();

        $response = $this->actingAs($interloper)
            ->deleteJson(route('api.comments.reactions.destroy', $reaction));

        $response->assertForbidden();
        $this->assertDatabaseHas('pivot_comments_reactions', [
            'id' => $reaction->id,
            'user_id' => $reactor->id,
        ]);
    }

    #[Group('authorization')]
    #[Test]
    public function officers_cannot_delete_another_users_reaction(): void
    {
        $comment = $this->createComment(User::factory()->raider()->create());
        $reactor = User::factory()->raider()->create();
        $officer = User::factory()->officer()->create();
        $reaction = CommentReaction::factory()->forComment($comment)->byUser($reactor)->create();

        $response = $this->actingAs($officer)
            ->deleteJson(route('api.comments.reactions.destroy', $reaction));

        $response->assertForbidden();
        $this->assertDatabaseHas('pivot_comments_reactions', ['id' => $reaction->id]);
    }

    // ==================== broadcasting ====================

    #[Test]
    #[Group('broadcasting')]
    public function storing_a_reaction_broadcasts_reaction_changed_to_others(): void
    {
        Event::fake([CommentReactionChanged::class]);

        $author = User::factory()->raider()->create();
        $reactor = User::factory()->raider()->create();
        $item = Item::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => Item::class,
            'commentable_id' => (string) $item->id,
            'user_id' => $author->id,
        ]);

        $this->actingAs($reactor)
            ->withHeader('X-Socket-ID', 'test-socket-id')
            ->postJson(route('api.comments.reactions.store'), ['comment_id' => $comment->id])
            ->assertCreated();

        Event::assertDispatched(
            CommentReactionChanged::class,
            fn (CommentReactionChanged $event) => $event->reaction->comment_id === $comment->id
                && $event->action === 'created'
                && $event->socket === 'test-socket-id',
        );
    }

    #[Test]
    #[Group('broadcasting')]
    public function destroying_a_reaction_broadcasts_reaction_changed_to_others(): void
    {
        Event::fake([CommentReactionChanged::class]);

        $author = User::factory()->create();
        $reactor = User::factory()->create();
        $item = Item::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => Item::class,
            'commentable_id' => (string) $item->id,
            'user_id' => $author->id,
        ]);
        $reaction = CommentReaction::factory()->forComment($comment)->byUser($reactor)->create();

        $this->actingAs($reactor)
            ->withHeader('X-Socket-ID', 'test-socket-id')
            ->deleteJson(route('api.comments.reactions.destroy', $reaction))
            ->assertNoContent();

        Event::assertDispatched(
            CommentReactionChanged::class,
            fn (CommentReactionChanged $event) => $event->reaction->comment_id === $comment->id
                && $event->action === 'deleted'
                && $event->socket === 'test-socket-id',
        );
    }

    // ==================== route separation ====================

    #[Test]
    public function the_reactions_segment_never_binds_as_a_comment_key(): void
    {
        $comment = $this->createComment(User::factory()->raider()->create());
        $reactor = User::factory()->raider()->create();
        $reaction = CommentReaction::factory()->forComment($comment)->byUser($reactor)->create();

        $this->actingAs($reactor)
            ->deleteJson(route('api.comments.reactions.destroy', $reaction))
            ->assertNoContent();

        $this->assertDatabaseMissing('pivot_comments_reactions', ['id' => $reaction->id]);
        $this->assertNotSoftDeleted('comments', ['id' => $comment->id]);
    }

    private function createItem(): Item
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        return Item::factory()->fromBoss($boss)->create();
    }

    /**
     * Create a comment on a fresh item, authored by the given user.
     */
    private function createComment(User $author): Comment
    {
        $item = $this->createItem();

        return Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $author->id,
        ]);
    }
}
