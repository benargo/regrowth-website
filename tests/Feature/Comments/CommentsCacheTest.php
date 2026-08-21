<?php

namespace Tests\Feature\Comments;

use App\Models\Comment;
use App\Models\DiscordRole;
use App\Models\Item;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('comments')]
#[Group('blizzard-integration')]
#[Group('loot')]
class CommentsCacheTest extends TestCase
{
    use MocksBlizzardServices;
    use MocksDiscordService;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->mockDiscordChannel();

        $this->mockItemService();

        $commentOnLootItems = Permission::firstOrCreate(['name' => 'comment-on-loot-items', 'guard_name' => 'web']);
        $editItems = Permission::firstOrCreate(['name' => 'edit-items', 'guard_name' => 'web']);

        $raiderRole = DiscordRole::firstOrCreate(['id' => '1265247017215594496'], ['name' => 'Raider', 'position' => 4, 'is_visible' => true]);
        $raiderRole->givePermissionTo($commentOnLootItems);

        $officerRole = DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 6, 'is_visible' => true]);
        $officerRole->givePermissionTo($commentOnLootItems);
        $officerRole->givePermissionTo($editItems);
    }

    // ==================== comment visibility ====================

    #[Test]
    public function new_comment_appears_after_creation(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        // First request shows no comments
        $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        // Create a new comment
        $this->actingAs($user)->postJson(route('api.comments.store'), [
            'commentable_type' => Item::class,
            'commentable_id' => (string) $item->id,
            'body' => 'Brand new comment',
        ]);

        // Next request should show the new comment
        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
        $response->assertSee('Brand new comment');
    }

    #[Test]
    public function updated_comment_appears_after_update(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $user->id,
            'body' => 'Original comment body',
        ]);

        // First request populates the page
        $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        // Update the comment
        $this->actingAs($user)->patchJson(route('api.comments.update', $comment), [
            'body' => 'Updated comment body',
        ]);

        // Next request should show the updated comment
        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
        $response->assertSee('Updated comment body');
    }

    #[Test]
    public function deleted_comment_disappears_after_deletion(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $user->id,
            'body' => 'Comment to be deleted',
        ]);

        // First request shows the comment
        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));
        $response->assertSee('Comment to be deleted');

        // Delete the comment
        $this->actingAs($user)->deleteJson(route('api.comments.destroy', $comment));

        // Next request should not show the deleted comment
        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
        $response->assertDontSee('Comment to be deleted');
    }

    protected function createItem(): Item
    {
        return Item::factory()->fromBoss()->withName('Test Item')->create();
    }
}
