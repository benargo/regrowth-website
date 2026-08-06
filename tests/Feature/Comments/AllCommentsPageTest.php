<?php

namespace Tests\Feature\Comments;

use App\Models\Comment;
use App\Models\DiscordRole;
use App\Models\Item;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('comments')]
#[Group('loot')]
class AllCommentsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $viewAllComments = Permission::firstOrCreate(['name' => 'view-all-comments', 'guard_name' => 'web']);

        DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 6, 'is_visible' => true])
            ->givePermissionTo($viewAllComments);

        DiscordRole::firstOrCreate(['id' => '1467994755953852590'], ['name' => 'Loot Councillor', 'position' => 5, 'is_visible' => true])
            ->givePermissionTo($viewAllComments);
    }

    #[Group('authorization')]
    #[Test]
    public function guest_users_cannot_access_comments_index(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('loot.comments.index'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function member_users_cannot_access_comments_index(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('loot.comments.index'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function raider_users_cannot_access_comments_index(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('loot.comments.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function loot_councillors_can_access_comments_index(): void
    {
        $user = User::factory()->member()->lootCouncillor()->create();

        $response = $this->actingAs($user)->get(route('loot.comments.index'));

        $response->assertOk();
    }

    #[Test]
    public function officers_can_access_comments_index(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('loot.comments.index'));

        $response->assertOk();
    }

    #[Test]
    public function comments_index_is_paginated(): void
    {
        $item1 = $this->createItem();
        $item2 = $this->createItem();
        $user = User::factory()->officer()->create();
        $commentAuthor = User::factory()->raider()->create();

        // Create 25 comments across two items
        Comment::factory()->count(15)->create([
            'commentable_id' => (string) $item1->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);
        Comment::factory()->count(10)->create([
            'commentable_id' => (string) $item2->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->actingAs($user)->get(route('loot.comments.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Comments/Index')
            ->has('comments.data', 20) // 20 per page
            ->has('comments.links')
            ->has('comments.meta')
        );
    }

    #[Test]
    public function comments_index_includes_item_data(): void
    {
        $item1 = $this->createItem();
        $item2 = $this->createItem();
        $user = User::factory()->officer()->create();
        $commentAuthor = User::factory()->raider()->create();

        Comment::factory()->count(3)->create([
            'commentable_id' => (string) $item1->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);
        Comment::factory()->count(2)->create([
            'commentable_id' => (string) $item2->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->actingAs($user)->get(route('loot.comments.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Comments/Index')
            ->has('comments.data', 5)
            ->has('comments.data.0.item')
            ->has('comments.data.0.user')
            ->has('comments.data.0.can')
        );
    }

    #[Test]
    public function comments_index_orders_by_latest(): void
    {
        $item = $this->createItem();
        $user = User::factory()->officer()->create();
        $commentAuthor = User::factory()->raider()->create();

        $oldComment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
            'body' => 'Old comment',
            'created_at' => now()->subDays(5),
        ]);

        $newComment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
            'body' => 'New comment',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('loot.comments.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('comments.data.0.id', $newComment->id)
            ->where('comments.data.1.id', $oldComment->id)
        );
    }

    protected function createItem(): Item
    {
        return Item::factory()->fromBoss()->withName('Test Item')->create();
    }
}
