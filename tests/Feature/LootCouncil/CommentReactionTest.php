<?php

namespace Tests\Feature\LootCouncil;

use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\CommentReaction;
use App\Models\LootCouncil\Item;
use App\Models\TBC\Boss;
use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use App\Models\User;
use App\Services\Blizzard\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CommentReactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockItemService();
    }

    protected function mockItemService(): void
    {
        $this->instance(
            ItemService::class,
            Mockery::mock(ItemService::class, function (MockInterface $mock) {
                $mock->shouldReceive('find')
                    ->andReturnUsing(fn (int $id) => [
                        'id' => $id,
                        'name' => "Test Item {$id}",
                    ]);

                $mock->shouldReceive('media')
                    ->andReturn([
                        'assets' => [
                            ['key' => 'icon', 'value' => 'https://example.com/icon.jpg'],
                        ],
                    ]);
            })
        );
    }

    protected function createItem(): Item
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        return Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);
    }

    // ==========================================
    // Store reaction authorization tests
    // ==========================================

    public function test_authenticated_users_can_react_to_comments_from_other_users(): void
    {
        $item = $this->createItem();
        $commentAuthor = User::factory()->raider()->create();
        $reactingUser = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->actingAs($reactingUser)->post(route('loot.comments.reactions.store', $comment));

        $response->assertRedirect();
        $this->assertDatabaseHas('lootcouncil_comments_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $reactingUser->id,
        ]);
    }

    public function test_users_cannot_react_to_their_own_comments(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('loot.comments.reactions.store', $comment));

        $response->assertForbidden();
        $this->assertDatabaseMissing('lootcouncil_comments_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_unauthenticated_users_cannot_react_to_comments(): void
    {
        $item = $this->createItem();
        $commentAuthor = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->post(route('loot.comments.reactions.store', $comment));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('lootcouncil_comments_reactions', 0);
    }

    public function test_guest_users_cannot_react_to_comments(): void
    {
        $item = $this->createItem();
        $commentAuthor = User::factory()->raider()->create();
        $guestUser = User::factory()->guest()->create();
        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->actingAs($guestUser)->post(route('loot.comments.reactions.store', $comment));

        $response->assertForbidden();
        $this->assertDatabaseMissing('lootcouncil_comments_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $guestUser->id,
        ]);
    }

    public function test_member_users_can_react_to_comments(): void
    {
        $item = $this->createItem();
        $commentAuthor = User::factory()->raider()->create();
        $memberUser = User::factory()->member()->create();
        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->actingAs($memberUser)->post(route('loot.comments.reactions.store', $comment));

        $response->assertRedirect();
        $this->assertDatabaseHas('lootcouncil_comments_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $memberUser->id,
        ]);
    }

    public function test_officer_users_can_react_to_comments(): void
    {
        $item = $this->createItem();
        $commentAuthor = User::factory()->raider()->create();
        $officerUser = User::factory()->officer()->create();
        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->actingAs($officerUser)->post(route('loot.comments.reactions.store', $comment));

        $response->assertRedirect();
        $this->assertDatabaseHas('lootcouncil_comments_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $officerUser->id,
        ]);
    }

    // ==========================================
    // Destroy reaction authorization tests
    // ==========================================

    public function test_users_can_delete_their_own_reactions(): void
    {
        $item = $this->createItem();
        $commentAuthor = User::factory()->raider()->create();
        $reactingUser = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
        ]);
        $reaction = CommentReaction::factory()->create([
            'comment_id' => $comment->id,
            'user_id' => $reactingUser->id,
        ]);

        $response = $this->actingAs($reactingUser)->delete(
            route('loot.comments.reactions.destroy', [$comment, $reaction])
        );

        $response->assertRedirect();
        $this->assertDatabaseMissing('lootcouncil_comments_reactions', [
            'id' => $reaction->id,
        ]);
    }

    public function test_unauthenticated_users_cannot_delete_reactions(): void
    {
        $item = $this->createItem();
        $commentAuthor = User::factory()->raider()->create();
        $reactingUser = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
        ]);
        $reaction = CommentReaction::factory()->create([
            'comment_id' => $comment->id,
            'user_id' => $reactingUser->id,
        ]);

        $response = $this->delete(route('loot.comments.reactions.destroy', [$comment, $reaction]));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('lootcouncil_comments_reactions', [
            'id' => $reaction->id,
        ]);
    }

    // ==========================================
    // Destroy reaction validation tests
    // ==========================================

    public function test_destroy_fails_if_reaction_does_not_belong_to_comment(): void
    {
        $item = $this->createItem();
        $commentAuthor = User::factory()->raider()->create();
        $reactingUser = User::factory()->raider()->create();

        $comment1 = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
        ]);
        $comment2 = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
        ]);

        $reaction = CommentReaction::factory()->create([
            'comment_id' => $comment2->id,
            'user_id' => $reactingUser->id,
        ]);

        $response = $this->actingAs($reactingUser)->delete(
            route('loot.comments.reactions.destroy', [$comment1, $reaction])
        );

        $response->assertSessionHasErrors('reaction');
        $this->assertDatabaseHas('lootcouncil_comments_reactions', [
            'id' => $reaction->id,
        ]);
    }

    public function test_destroy_succeeds_when_reaction_belongs_to_comment(): void
    {
        $item = $this->createItem();
        $commentAuthor = User::factory()->raider()->create();
        $reactingUser = User::factory()->raider()->create();

        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
        ]);

        $reaction = CommentReaction::factory()->create([
            'comment_id' => $comment->id,
            'user_id' => $reactingUser->id,
        ]);

        $response = $this->actingAs($reactingUser)->delete(
            route('loot.comments.reactions.destroy', [$comment, $reaction])
        );

        $response->assertRedirect();
        $this->assertDatabaseMissing('lootcouncil_comments_reactions', [
            'id' => $reaction->id,
        ]);
    }

    // ==========================================
    // Model validation tests
    // ==========================================

    public function test_model_prevents_user_from_reacting_to_own_comment_directly(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        CommentReaction::create([
            'comment_id' => $comment->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_cannot_react_to_same_comment_twice(): void
    {
        $item = $this->createItem();
        $commentAuthor = User::factory()->raider()->create();
        $reactingUser = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
        ]);

        CommentReaction::factory()->create([
            'comment_id' => $comment->id,
            'user_id' => $reactingUser->id,
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        CommentReaction::factory()->create([
            'comment_id' => $comment->id,
            'user_id' => $reactingUser->id,
        ]);
    }
}
