<?php

namespace Tests\Feature\Api;

use App\Events\Broadcasts\CommentChanged;
use App\Events\Broadcasts\CommentPosted;
use App\Events\Broadcasts\CommentRemoved;
use App\Models\Boss;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\DiscordRole;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use App\Notifications\NewLootCouncilComment;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Resources\Channel as DiscordChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('comments')]
#[Group('loot')]
#[Group('discord-integration')]
class CommentControllerTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockDiscordChannel()->shouldReceive('createMessage')->andReturn($this->makeDiscordMessage());

        $commentOnLootItems = Permission::firstOrCreate(['name' => 'comment-on-loot-items', 'guard_name' => 'web']);
        $deleteAnyComment = Permission::firstOrCreate(['name' => 'delete-any-comment', 'guard_name' => 'web']);
        $editAnyComment = Permission::firstOrCreate(['name' => 'edit-any-comment', 'guard_name' => 'web']);
        $markCommentAsResolved = Permission::firstOrCreate(['name' => 'mark-comment-as-resolved', 'guard_name' => 'web']);

        $raiderRole = DiscordRole::factory()->raider()->create();
        $raiderRole->givePermissionTo($commentOnLootItems);

        $officerRole = DiscordRole::factory()->officer()->create();
        $officerRole->givePermissionTo($commentOnLootItems);
        $officerRole->givePermissionTo($deleteAnyComment);
        $officerRole->givePermissionTo($editAnyComment);
        $officerRole->givePermissionTo($markCommentAsResolved);
    }

    // ==================== store — authorization ====================

    #[Group('authorization')]
    #[Test]
    public function unauthenticated_users_cannot_create_comments(): void
    {
        $item = $this->createItem();

        $response = $this->postJson(route('api.comments.store'), $this->storePayload($item));

        $response->assertUnauthorized();
        $this->assertDatabaseCount('comments', 0);
    }

    #[Group('authorization')]
    #[Test]
    public function guest_users_cannot_create_comments(): void
    {
        $item = $this->createItem();
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.comments.store'), $this->storePayload($item, 'Test comment'));

        $response->assertForbidden();
        $this->assertDatabaseMissing('comments', ['body' => 'Test comment']);
    }

    #[Group('authorization')]
    #[Test]
    public function member_users_cannot_create_comments(): void
    {
        $item = $this->createItem();
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.comments.store'), $this->storePayload($item, 'Test comment'));

        $response->assertForbidden();
        $this->assertDatabaseMissing('comments', ['body' => 'Test comment']);
    }

    #[Test]
    public function raider_users_can_create_comments(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.comments.store'), $this->storePayload($item, 'Test comment from raider'));

        $response->assertCreated();
        $this->assertDatabaseHas('comments', [
            'body' => 'Test comment from raider',
            'user_id' => $user->id,
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
        ]);
    }

    #[Test]
    public function officer_users_can_create_comments(): void
    {
        $item = $this->createItem();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.comments.store'), $this->storePayload($item, 'Test comment from officer'));

        $response->assertCreated();
        $this->assertDatabaseHas('comments', [
            'body' => 'Test comment from officer',
            'user_id' => $user->id,
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
        ]);
    }

    // ==================== store — validation ====================

    #[Group('validation')]
    #[Test]
    public function comment_creation_fails_with_empty_body(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.comments.store'), $this->storePayload($item, ''));

        $response->assertJsonValidationErrors('body');
        $this->assertDatabaseCount('comments', 0);
    }

    #[Group('validation')]
    #[Test]
    public function comment_creation_fails_with_body_too_short(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.comments.store'), $this->storePayload($item, 'ab'));

        $response->assertJsonValidationErrors('body');
        $this->assertDatabaseCount('comments', 0);
    }

    #[Group('validation')]
    #[Test]
    public function comment_creation_fails_with_body_too_long(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.comments.store'), $this->storePayload($item, str_repeat('a', 5001)));

        $response->assertJsonValidationErrors('body');
        $this->assertDatabaseCount('comments', 0);
    }

    #[Group('validation')]
    #[Test]
    public function comment_creation_fails_without_a_commentable_type(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->postJson(route('api.comments.store'), [
            'commentable_id' => (string) $item->id,
            'body' => 'A perfectly reasonable comment',
        ]);

        $response->assertJsonValidationErrors('commentable_type');
        $this->assertDatabaseCount('comments', 0);
    }

    #[Group('validation')]
    #[Test]
    public function comment_creation_fails_with_a_bogus_commentable_type(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->postJson(route('api.comments.store'), [
            'commentable_type' => 'App\\Models\\NotAThing',
            'commentable_id' => (string) $item->id,
            'body' => 'A perfectly reasonable comment',
        ]);

        $response->assertJsonValidationErrors('commentable_type');
        $this->assertDatabaseCount('comments', 0);
    }

    #[Group('validation')]
    #[Test]
    public function comment_creation_fails_with_a_real_but_disallowed_commentable_type(): void
    {
        $this->createItem();
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->postJson(route('api.comments.store'), [
            'commentable_type' => User::class,
            'commentable_id' => $user->id,
            'body' => 'A perfectly reasonable comment',
        ]);

        $response->assertJsonValidationErrors('commentable_type');
        $this->assertDatabaseCount('comments', 0);
    }

    #[Group('validation')]
    #[Test]
    public function comment_creation_fails_when_the_commentable_does_not_exist(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->postJson(route('api.comments.store'), [
            'commentable_type' => Item::class,
            'commentable_id' => '999999',
            'body' => 'A perfectly reasonable comment',
        ]);

        $response->assertJsonValidationErrors('commentable_id');
        $this->assertDatabaseCount('comments', 0);
    }

    // ==================== store — behaviour ====================

    #[Test]
    public function store_returns_the_created_comment_as_a_resource(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.comments.store'), $this->storePayload($item, 'Freshly posted'));

        $response->assertCreated();
        $response->assertJsonStructure([
            'data' => ['id', 'body', 'commentable', 'user', 'reactions', 'is_resolved', 'created_at', 'updated_at', 'permissions'],
        ]);
        $response->assertJsonPath('data.body', 'Freshly posted');
        $response->assertJsonPath('data.is_resolved', false);
        $response->assertJsonPath('data.user.id', $user->id);
        $response->assertJsonPath('data.reactions', []);
    }

    #[Test]
    public function new_comments_default_to_unresolved(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        $this->actingAs($user)
            ->postJson(route('api.comments.store'), $this->storePayload($item, 'Test comment'))
            ->assertCreated();

        $this->assertDatabaseHas('comments', [
            'body' => 'Test comment',
            'is_resolved' => false,
        ]);
    }

    #[Test]
    public function store_dispatches_the_loot_council_notification(): void
    {
        Notification::fake();

        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        $this->actingAs($user)
            ->postJson(route('api.comments.store'), $this->storePayload($item, 'Notify the council'))
            ->assertCreated();

        Notification::assertSentTo(
            new NotifiableChannel(DiscordChannel::from(['id' => '123456789'])),
            NewLootCouncilComment::class
        );
    }

    #[Group('authorization')]
    #[Test]
    public function a_user_without_permission_cannot_post_a_reply(): void
    {
        $user = User::factory()->create();
        $root = Comment::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.comments.store'), [
            'commentable_id' => (string) $root->commentable_id,
            'commentable_type' => Item::class,
            'body' => 'Unauthorized reply',
            'parent_id' => $root->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('comments', ['body' => 'Unauthorized reply']);
    }

    // ==================== update — authorization ====================

    #[Group('authorization')]
    #[Test]
    public function unauthenticated_users_cannot_update_comments(): void
    {
        $comment = Comment::factory()->create(['body' => 'Original body']);

        $response = $this->patchJson(route('api.comments.update', $comment), ['body' => 'Rewritten body']);

        $response->assertUnauthorized();
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'body' => 'Original body']);
    }

    #[Test]
    public function raiders_can_edit_their_own_comments(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $user->id,
            'body' => 'Original comment',
        ]);

        $response = $this->actingAs($user)
            ->patchJson(route('api.comments.update', $comment), ['body' => 'Updated comment']);

        $response->assertOk();
        $response->assertJsonPath('data.body', 'Updated comment');
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'body' => 'Updated comment']);
    }

    #[Group('authorization')]
    #[Test]
    public function raiders_cannot_edit_other_users_comments(): void
    {
        $item = $this->createItem();
        $raider = User::factory()->raider()->create();
        $otherUser = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $otherUser->id,
            'body' => 'Original comment',
        ]);

        $response = $this->actingAs($raider)
            ->patchJson(route('api.comments.update', $comment), ['body' => 'Updated comment']);

        $response->assertForbidden();
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'body' => 'Original comment']);
    }

    #[Group('authorization')]
    #[Test]
    public function raiders_cannot_edit_resolved_comments(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->resolved()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $user->id,
            'body' => 'Original resolved comment',
        ]);

        $response = $this->actingAs($user)
            ->patchJson(route('api.comments.update', $comment), ['body' => 'Updated comment']);

        $response->assertForbidden();
        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'body' => 'Original resolved comment',
        ]);
    }

    #[Test]
    public function officers_can_edit_resolved_comments(): void
    {
        $item = $this->createItem();
        $raider = User::factory()->raider()->create();
        $officer = User::factory()->officer()->create();
        $comment = Comment::factory()->resolved()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $raider->id,
            'body' => 'Original resolved comment',
        ]);

        $response = $this->actingAs($officer)
            ->patchJson(route('api.comments.update', $comment), ['body' => 'Updated by officer']);

        $response->assertOk();
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'body' => 'Updated by officer']);
    }

    // ==================== update — in-place semantics ====================

    #[Test]
    public function update_keeps_the_same_comment_row(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $user->id,
            'body' => 'Original comment',
        ]);

        $response = $this->actingAs($user)
            ->patchJson(route('api.comments.update', $comment), ['body' => 'Updated comment']);

        $response->assertOk();
        $response->assertJsonPath('data.id', $comment->id);

        $this->assertDatabaseCount('comments', 1);
        $this->assertNotSoftDeleted('comments', ['id' => $comment->id]);
    }

    #[Test]
    public function update_does_not_change_created_at(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $originalTime = now()->subDays(5);
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $user->id,
            'body' => 'Original comment',
            'created_at' => $originalTime,
        ]);

        $this->actingAs($user)
            ->patchJson(route('api.comments.update', $comment), ['body' => 'Updated comment'])
            ->assertOk();

        $this->assertSame(
            $originalTime->format('Y-m-d H:i:s'),
            $comment->fresh()->created_at->format('Y-m-d H:i:s')
        );
    }

    #[Test]
    public function update_preserves_resolved_status_when_only_body_is_sent(): void
    {
        $item = $this->createItem();
        $officer = User::factory()->officer()->create();
        $comment = Comment::factory()->resolved()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $officer->id,
            'body' => 'Original comment',
        ]);

        $this->actingAs($officer)
            ->patchJson(route('api.comments.update', $comment), ['body' => 'Updated comment'])
            ->assertOk();

        $this->assertTrue($comment->fresh()->is_resolved);
    }

    #[Test]
    public function update_preserves_body_when_only_resolved_status_is_sent(): void
    {
        $item = $this->createItem();
        $raider = User::factory()->raider()->create();
        $officer = User::factory()->officer()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $raider->id,
            'body' => 'Untouched body',
        ]);

        $this->actingAs($officer)
            ->patchJson(route('api.comments.update', $comment), ['isResolved' => true])
            ->assertOk();

        $this->assertSame('Untouched body', $comment->fresh()->body);
    }

    // ==================== update — resolve ====================

    #[Test]
    public function officers_can_mark_a_comment_as_resolved(): void
    {
        $item = $this->createItem();
        $raider = User::factory()->raider()->create();
        $officer = User::factory()->officer()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $raider->id,
            'is_resolved' => false,
        ]);

        $response = $this->actingAs($officer)
            ->patchJson(route('api.comments.update', $comment), ['isResolved' => true]);

        $response->assertOk();
        $response->assertJsonPath('data.is_resolved', true);
        $this->assertTrue($comment->fresh()->is_resolved);
    }

    #[Test]
    public function officers_can_mark_a_comment_as_unresolved(): void
    {
        $item = $this->createItem();
        $raider = User::factory()->raider()->create();
        $officer = User::factory()->officer()->create();
        $comment = Comment::factory()->resolved()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $raider->id,
        ]);

        $response = $this->actingAs($officer)
            ->patchJson(route('api.comments.update', $comment), ['isResolved' => false]);

        $response->assertOk();
        $response->assertJsonPath('data.is_resolved', false);
        $this->assertFalse($comment->fresh()->is_resolved);
    }

    #[Group('authorization')]
    #[Test]
    public function raiders_cannot_resolve_their_own_comment(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $user->id,
            'is_resolved' => false,
        ]);

        $response = $this->actingAs($user)->patchJson(route('api.comments.update', $comment), [
            'body' => $comment->body,
            'isResolved' => true,
        ]);

        $response->assertForbidden();
        $this->assertFalse($comment->fresh()->is_resolved);
    }

    #[Group('authorization')]
    #[Test]
    public function a_reply_cannot_be_marked_as_resolved(): void
    {
        $officer = User::factory()->officer()->create();
        $root = Comment::factory()->create();
        $reply = Comment::factory()->replyTo($root)->create();

        $response = $this->actingAs($officer)->patchJson(
            route('api.comments.update', $reply),
            ['isResolved' => true],
        );

        $response->assertForbidden();
        $this->assertFalse($reply->fresh()->is_resolved);
    }

    // ==================== update — revisions ====================

    #[Test]
    public function update_records_a_revision_holding_the_prior_body(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $user->id,
            'body' => 'The body before the edit',
        ]);

        $this->actingAs($user)
            ->patchJson(route('api.comments.update', $comment), ['body' => 'The body after the edit'])
            ->assertOk();

        $this->assertDatabaseCount('comment_revisions', 1);
        $this->assertDatabaseHas('comment_revisions', [
            'comment_id' => $comment->id,
            'body' => 'The body before the edit',
            'edited_by' => $user->id,
        ]);
    }

    #[Test]
    public function update_records_the_editor_not_the_author(): void
    {
        $item = $this->createItem();
        $author = User::factory()->raider()->create();
        $officer = User::factory()->officer()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $author->id,
            'body' => 'Written by the author',
        ]);

        $this->actingAs($officer)
            ->patchJson(route('api.comments.update', $comment), ['body' => 'Moderated by an officer'])
            ->assertOk();

        $this->assertDatabaseHas('comment_revisions', [
            'comment_id' => $comment->id,
            'body' => 'Written by the author',
            'edited_by' => $officer->id,
        ]);
        $this->assertDatabaseMissing('comment_revisions', ['edited_by' => $author->id]);
    }

    #[Test]
    public function resolving_a_comment_records_no_revision(): void
    {
        $item = $this->createItem();
        $raider = User::factory()->raider()->create();
        $officer = User::factory()->officer()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $raider->id,
            'is_resolved' => false,
        ]);

        $this->actingAs($officer)
            ->patchJson(route('api.comments.update', $comment), ['isResolved' => true])
            ->assertOk();

        $this->assertDatabaseCount('comment_revisions', 0);
    }

    #[Test]
    public function resubmitting_an_unchanged_body_records_no_revision(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $user->id,
            'body' => 'Exactly the same body',
        ]);

        $this->actingAs($user)
            ->patchJson(route('api.comments.update', $comment), ['body' => 'Exactly the same body'])
            ->assertOk();

        $this->assertDatabaseCount('comment_revisions', 0);
    }

    // ==================== update — regression guard ====================

    #[Test]
    public function resolving_a_comment_preserves_its_reactions(): void
    {
        $item = $this->createItem();
        $author = User::factory()->raider()->create();
        $officer = User::factory()->officer()->create();
        $reactorOne = User::factory()->raider()->create();
        $reactorTwo = User::factory()->member()->create();

        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $author->id,
            'is_resolved' => false,
        ]);

        CommentReaction::factory()->forComment($comment)->byUser($reactorOne)->create();
        CommentReaction::factory()->forComment($comment)->byUser($reactorTwo)->create();

        $this->assertDatabaseCount('pivot_comments_reactions', 2);

        $response = $this->actingAs($officer)
            ->patchJson(route('api.comments.update', $comment), ['isResolved' => true]);

        $response->assertOk();

        // The bug this refactor exists to fix: copy-on-write changed the comment's
        // primary key, orphaning every pivot_comments_reactions row keyed on it.
        $this->assertDatabaseCount('pivot_comments_reactions', 2);
        $this->assertDatabaseHas('pivot_comments_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $reactorOne->id,
        ]);
        $this->assertDatabaseHas('pivot_comments_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $reactorTwo->id,
        ]);
        $this->assertCount(2, $comment->fresh()->reactions);
    }

    #[Test]
    public function editing_a_comment_body_preserves_its_reactions(): void
    {
        $item = $this->createItem();
        $author = User::factory()->raider()->create();
        $reactor = User::factory()->raider()->create();

        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $author->id,
            'body' => 'Original body',
        ]);

        CommentReaction::factory()->forComment($comment)->byUser($reactor)->create();

        $this->actingAs($author)
            ->patchJson(route('api.comments.update', $comment), ['body' => 'Edited body'])
            ->assertOk();

        $this->assertDatabaseHas('pivot_comments_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $reactor->id,
        ]);
        $this->assertCount(1, $comment->fresh()->reactions);
    }

    #[Test]
    public function the_resolved_comments_reactions_appear_in_the_response(): void
    {
        $item = $this->createItem();
        $author = User::factory()->raider()->create();
        $officer = User::factory()->officer()->create();
        $reactor = User::factory()->raider()->create();

        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $author->id,
            'is_resolved' => false,
        ]);

        CommentReaction::factory()->forComment($comment)->byUser($reactor)->create();

        $response = $this->actingAs($officer)
            ->patchJson(route('api.comments.update', $comment), ['isResolved' => true]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data.reactions');
        $response->assertJsonPath('data.reactions.0.comment_id', $comment->id);
        $response->assertJsonPath('data.reactions.0.user.id', $reactor->id);
    }

    // ==================== destroy ====================

    #[Group('authorization')]
    #[Test]
    public function unauthenticated_users_cannot_delete_comments(): void
    {
        $comment = Comment::factory()->create();

        $response = $this->deleteJson(route('api.comments.destroy', $comment));

        $response->assertUnauthorized();
        $this->assertNotSoftDeleted('comments', ['id' => $comment->id]);
    }

    #[Test]
    public function raiders_can_delete_their_own_comments(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->deleteJson(route('api.comments.destroy', $comment));

        $response->assertNoContent();
        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    #[Group('authorization')]
    #[Test]
    public function raiders_cannot_delete_other_users_comments(): void
    {
        $item = $this->createItem();
        $raider = User::factory()->raider()->create();
        $otherUser = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($raider)->deleteJson(route('api.comments.destroy', $comment));

        $response->assertForbidden();
        $this->assertNotSoftDeleted('comments', ['id' => $comment->id]);
    }

    #[Test]
    public function officers_can_delete_any_comment(): void
    {
        $item = $this->createItem();
        $officer = User::factory()->officer()->create();
        $otherUser = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($officer)->deleteJson(route('api.comments.destroy', $comment));

        $response->assertNoContent();
        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    #[Test]
    public function deleted_comment_tracks_deleted_by_user(): void
    {
        $item = $this->createItem();
        $officer = User::factory()->officer()->create();
        $commentAuthor = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);

        $this->actingAs($officer)
            ->deleteJson(route('api.comments.destroy', $comment))
            ->assertNoContent();

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'deleted_by' => $officer->id,
        ]);
    }

    // ==================== broadcasting ====================

    #[Test]
    #[Group('broadcasting')]
    public function storing_a_comment_broadcasts_comment_posted_to_others(): void
    {
        Event::fake([CommentPosted::class]);

        $user = User::factory()->raider()->create();
        $item = Item::factory()->create();

        $this->actingAs($user)
            ->withHeader('X-Socket-ID', 'test-socket-id')
            ->postJson(route('api.comments.store'), [
                'commentable_type' => Item::class,
                'commentable_id' => (string) $item->id,
                'body' => 'A brand new comment.',
            ])
            ->assertCreated();

        Event::assertDispatched(
            CommentPosted::class,
            fn (CommentPosted $event) => $event->comment->commentable_id == $item->id
                && $event->socket === 'test-socket-id',
        );
    }

    #[Test]
    #[Group('broadcasting')]
    public function updating_a_comment_broadcasts_comment_changed_to_others(): void
    {
        Event::fake([CommentChanged::class]);

        $user = User::factory()->create();
        $item = Item::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => Item::class,
            'commentable_id' => (string) $item->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withHeader('X-Socket-ID', 'test-socket-id')
            ->patchJson(route('api.comments.update', $comment), ['body' => 'An edited body.'])
            ->assertOk();

        Event::assertDispatched(
            CommentChanged::class,
            fn (CommentChanged $event) => $event->comment->id === $comment->id
                && $event->socket === 'test-socket-id',
        );
    }

    #[Test]
    #[Group('broadcasting')]
    public function destroying_a_comment_broadcasts_comment_removed_to_others(): void
    {
        Event::fake([CommentRemoved::class]);

        $user = User::factory()->create();
        $item = Item::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => Item::class,
            'commentable_id' => (string) $item->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withHeader('X-Socket-ID', 'test-socket-id')
            ->deleteJson(route('api.comments.destroy', $comment))
            ->assertNoContent();

        Event::assertDispatched(
            CommentRemoved::class,
            fn (CommentRemoved $event) => $event->comment->id === $comment->id
                && $event->socket === 'test-socket-id',
        );
    }

    // ==================== helpers ====================

    private function createItem(): Item
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        return Item::factory()->fromBoss($boss)->create();
    }

    /**
     * Build a valid `store` payload for the given item.
     *
     * @return array<string, string>
     */
    private function storePayload(Item $item, string $body = 'A perfectly reasonable comment'): array
    {
        return [
            'commentable_type' => Item::class,
            'commentable_id' => (string) $item->id,
            'body' => $body,
        ];
    }
}
