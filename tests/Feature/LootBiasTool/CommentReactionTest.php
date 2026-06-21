<?php

namespace Tests\Feature\LootBiasTool;

use App\Models\Boss;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\DiscordRole;
use App\Models\Item;
use App\Models\Permission;
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

#[Group('loot')]
class CommentReactionTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockItemService();

        $reactToComments = Permission::firstOrCreate(['name' => 'react-to-comments', 'guard_name' => 'web']);

        $officerRole = DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 6, 'is_visible' => true]);
        $officerRole->givePermissionTo($reactToComments);

        $raiderRole = DiscordRole::firstOrCreate(['id' => '1265247017215594496'], ['name' => 'Raider', 'position' => 4, 'is_visible' => true]);
        $raiderRole->givePermissionTo($reactToComments);

        $memberRole = DiscordRole::firstOrCreate(['id' => '829022020301094922'], ['name' => 'Member', 'position' => 3, 'is_visible' => true]);
        $memberRole->givePermissionTo($reactToComments);
    }

    // ==========================================
    // Store reaction authorization tests
    // ==========================================

    #[Test]
    public function authenticated_users_can_react_to_comments_from_other_users(): void
    {
        $item = $this->createItem();
        $commentAuthor = User::factory()->raider()->create();
        $reactingUser = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->actingAs($reactingUser)->post(route('loot.comments.reactions.store', $comment));

        $response->assertRedirect();
        $this->assertDatabaseHas('pivot_comments_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $reactingUser->id,
        ]);
    }

    #[Group('authorization')]
    #[Test]
    public function users_cannot_react_to_their_own_comments(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('loot.comments.reactions.store', $comment));

        $response->assertForbidden();
        $this->assertDatabaseMissing('pivot_comments_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function unauthenticated_users_cannot_react_to_comments(): void
    {
        $item = $this->createItem();
        $commentAuthor = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->post(route('loot.comments.reactions.store', $comment));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('pivot_comments_reactions', 0);
    }

    #[Group('authorization')]
    #[Test]
    public function guest_users_cannot_react_to_comments(): void
    {
        $item = $this->createItem();
        $commentAuthor = User::factory()->raider()->create();
        $guestUser = User::factory()->guest()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->actingAs($guestUser)->post(route('loot.comments.reactions.store', $comment));

        $response->assertForbidden();
        $this->assertDatabaseMissing('pivot_comments_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $guestUser->id,
        ]);
    }

    #[Test]
    public function member_users_can_react_to_comments(): void
    {
        $item = $this->createItem();
        $commentAuthor = User::factory()->raider()->create();
        $memberUser = User::factory()->member()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->actingAs($memberUser)->post(route('loot.comments.reactions.store', $comment));

        $response->assertRedirect();
        $this->assertDatabaseHas('pivot_comments_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $memberUser->id,
        ]);
    }

    #[Test]
    public function officer_users_can_react_to_comments(): void
    {
        $item = $this->createItem();
        $commentAuthor = User::factory()->raider()->create();
        $officerUser = User::factory()->officer()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->actingAs($officerUser)->post(route('loot.comments.reactions.store', $comment));

        $response->assertRedirect();
        $this->assertDatabaseHas('pivot_comments_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $officerUser->id,
        ]);
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

    protected function createItem(): Item
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        return Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);
    }
}
