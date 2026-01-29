<?php

namespace Tests\Feature\Loot;

use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\ItemComment;
use App\Models\TBC\Boss;
use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use App\Models\User;
use App\Services\Blizzard\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ItemCommentsTest extends TestCase
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
    // Authorization tests for creating comments
    // ==========================================

    public function test_guest_users_cannot_create_comments(): void
    {
        $item = $this->createItem();
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->post(route('loot.items.comments.store', $item), [
            'body' => 'Test comment',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('lootcouncil_item_comments', ['body' => 'Test comment']);
    }

    public function test_member_users_cannot_create_comments(): void
    {
        $item = $this->createItem();
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->post(route('loot.items.comments.store', $item), [
            'body' => 'Test comment',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('lootcouncil_item_comments', ['body' => 'Test comment']);
    }

    public function test_raider_users_can_create_comments(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->post(route('loot.items.comments.store', $item), [
            'body' => 'Test comment from raider',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('lootcouncil_item_comments', [
            'body' => 'Test comment from raider',
            'user_id' => $user->id,
            'item_id' => $item->id,
        ]);
    }

    public function test_officer_users_can_create_comments(): void
    {
        $item = $this->createItem();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('loot.items.comments.store', $item), [
            'body' => 'Test comment from officer',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('lootcouncil_item_comments', [
            'body' => 'Test comment from officer',
            'user_id' => $user->id,
            'item_id' => $item->id,
        ]);
    }

    // ==========================================
    // Validation tests
    // ==========================================

    public function test_comment_creation_fails_with_empty_body(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->post(route('loot.items.comments.store', $item), [
            'body' => '',
        ]);

        $response->assertSessionHasErrors('body');
        $this->assertDatabaseCount('lootcouncil_item_comments', 0);
    }

    public function test_comment_creation_fails_with_body_too_short(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->post(route('loot.items.comments.store', $item), [
            'body' => 'ab',
        ]);

        $response->assertSessionHasErrors('body');
        $this->assertDatabaseCount('lootcouncil_item_comments', 0);
    }

    public function test_comment_creation_fails_with_body_too_long(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->post(route('loot.items.comments.store', $item), [
            'body' => str_repeat('a', 5001),
        ]);

        $response->assertSessionHasErrors('body');
        $this->assertDatabaseCount('lootcouncil_item_comments', 0);
    }

    // ==========================================
    // Delete authorization tests
    // ==========================================

    public function test_raiders_can_delete_their_own_comments(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = ItemComment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->delete(route('loot.items.comments.destroy', [$item, $comment]));

        $response->assertRedirect();
        $this->assertSoftDeleted('lootcouncil_item_comments', ['id' => $comment->id]);
    }

    public function test_raiders_cannot_delete_other_users_comments(): void
    {
        $item = $this->createItem();
        $raider = User::factory()->raider()->create();
        $otherUser = User::factory()->raider()->create();
        $comment = ItemComment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($raider)->delete(route('loot.items.comments.destroy', [$item, $comment]));

        $response->assertForbidden();
        $this->assertNotSoftDeleted('lootcouncil_item_comments', ['id' => $comment->id]);
    }

    public function test_officers_can_delete_any_comment(): void
    {
        $item = $this->createItem();
        $officer = User::factory()->officer()->create();
        $otherUser = User::factory()->raider()->create();
        $comment = ItemComment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($officer)->delete(route('loot.items.comments.destroy', [$item, $comment]));

        $response->assertRedirect();
        $this->assertSoftDeleted('lootcouncil_item_comments', ['id' => $comment->id]);
    }

    public function test_deleted_comment_tracks_deleted_by_user(): void
    {
        $item = $this->createItem();
        $officer = User::factory()->officer()->create();
        $commentAuthor = User::factory()->raider()->create();
        $comment = ItemComment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
        ]);

        $this->actingAs($officer)->delete(route('loot.items.comments.destroy', [$item, $comment]));

        $this->assertDatabaseHas('lootcouncil_item_comments', [
            'id' => $comment->id,
            'deleted_by' => $officer->id,
        ]);
    }

    // ==========================================
    // Edit authorization tests
    // ==========================================

    public function test_raiders_can_edit_their_own_comments(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = ItemComment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'body' => 'Original comment',
        ]);

        $response = $this->actingAs($user)->put(route('loot.items.comments.update', [$item, $comment]), [
            'body' => 'Updated comment',
        ]);

        $response->assertRedirect();
    }

    public function test_raiders_cannot_edit_other_users_comments(): void
    {
        $item = $this->createItem();
        $raider = User::factory()->raider()->create();
        $otherUser = User::factory()->raider()->create();
        $comment = ItemComment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $otherUser->id,
            'body' => 'Original comment',
        ]);

        $response = $this->actingAs($raider)->put(route('loot.items.comments.update', [$item, $comment]), [
            'body' => 'Updated comment',
        ]);

        $response->assertForbidden();
    }

    public function test_edit_creates_new_comment_and_soft_deletes_old(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = ItemComment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'body' => 'Original comment',
        ]);
        $originalId = $comment->id;

        $this->actingAs($user)->put(route('loot.items.comments.update', [$item, $comment]), [
            'body' => 'Updated comment',
        ]);

        // Original comment should be soft deleted
        $this->assertSoftDeleted('lootcouncil_item_comments', [
            'id' => $originalId,
            'body' => 'Original comment',
        ]);

        // New comment should exist with updated body
        $this->assertDatabaseHas('lootcouncil_item_comments', [
            'item_id' => $item->id,
            'user_id' => $user->id,
            'body' => 'Updated comment',
        ]);
    }

    public function test_edited_comment_preserves_original_timestamp(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $originalTime = now()->subDays(5);
        $comment = ItemComment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'body' => 'Original comment',
            'created_at' => $originalTime,
        ]);

        $this->actingAs($user)->put(route('loot.items.comments.update', [$item, $comment]), [
            'body' => 'Updated comment',
        ]);

        // The new comment should have the same created_at as the original
        $newComment = ItemComment::where('body', 'Updated comment')->first();
        $this->assertEquals(
            $originalTime->format('Y-m-d H:i:s'),
            $newComment->created_at->format('Y-m-d H:i:s')
        );
    }

    public function test_edit_tracks_deleted_by_for_original_comment(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = ItemComment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'body' => 'Original comment',
        ]);
        $originalId = $comment->id;

        $this->actingAs($user)->put(route('loot.items.comments.update', [$item, $comment]), [
            'body' => 'Updated comment',
        ]);

        $this->assertDatabaseHas('lootcouncil_item_comments', [
            'id' => $originalId,
            'deleted_by' => $user->id,
        ]);
    }

    // ==========================================
    // Show page tests
    // ==========================================

    public function test_item_show_page_includes_comments(): void
    {
        $item = $this->createItem();
        $user = User::factory()->member()->create();
        $commentAuthor = User::factory()->raider()->create();
        ItemComment::factory()->count(3)->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->actingAs($user)->get(route('loot.items.show', $item));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/ItemShow')
            ->has('comments.data', 3)
        );
    }

    public function test_item_show_page_includes_can_create_comment_for_raiders(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('loot.items.show', $item));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('can.create_comment', true)
        );
    }

    public function test_item_show_page_includes_can_create_comment_false_for_members(): void
    {
        $item = $this->createItem();
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('loot.items.show', $item));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('can.create_comment', false)
        );
    }

    public function test_comments_are_paginated(): void
    {
        $item = $this->createItem();
        $user = User::factory()->member()->create();
        $commentAuthor = User::factory()->raider()->create();
        ItemComment::factory()->count(15)->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->actingAs($user)->get(route('loot.items.show', $item));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('comments.data', 10) // 10 per page
            ->has('comments.links')
            ->has('comments.meta')
        );
    }

    public function test_comments_are_ordered_by_latest(): void
    {
        $item = $this->createItem();
        $user = User::factory()->member()->create();
        $commentAuthor = User::factory()->raider()->create();

        $oldComment = ItemComment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
            'body' => 'Old comment',
            'created_at' => now()->subDays(5),
        ]);

        $newComment = ItemComment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
            'body' => 'New comment',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('loot.items.show', $item));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('comments.data.0.id', $newComment->id)
            ->where('comments.data.1.id', $oldComment->id)
        );
    }

    public function test_comment_resource_includes_authorization_flags(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = ItemComment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('loot.items.show', $item));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('comments.data.0.can.edit')
            ->has('comments.data.0.can.delete')
        );
    }
}
