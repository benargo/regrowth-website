<?php

namespace Tests\Feature\LootCouncil;

use App\Models\DiscordRole;
use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
use App\Models\Permission;
use App\Models\TBC\Boss;
use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use App\Models\User;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\MediaService;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\Channel as DiscordChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommentCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->mock(Discord::class, function (MockInterface $mock) {
            $mock->shouldReceive('getChannel')
                ->andReturn(DiscordChannel::from(['id' => '123456789']));
        });

        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('findItem')->andReturnUsing(fn (int $id) => ['id' => $id, 'name' => "Test Item {$id}"]);
            $mock->shouldReceive('findMedia')->andReturn(['assets' => []]);
        });

        $this->mock(MediaService::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')->andReturn(null);
        });

        $viewLootBiasTool = Permission::firstOrCreate(['name' => 'view-loot-bias-tool', 'guard_name' => 'web']);
        $commentOnLootItems = Permission::firstOrCreate(['name' => 'comment-on-loot-items', 'guard_name' => 'web']);
        $editItems = Permission::firstOrCreate(['name' => 'edit-items', 'guard_name' => 'web']);

        DiscordRole::firstOrCreate(['id' => '829022020301094922'], ['name' => 'Member', 'position' => 2, 'is_visible' => true])->givePermissionTo($viewLootBiasTool);

        $raiderRole = DiscordRole::firstOrCreate(['id' => '1265247017215594496'], ['name' => 'Raider', 'position' => 4, 'is_visible' => true]);
        $raiderRole->givePermissionTo($viewLootBiasTool);
        $raiderRole->givePermissionTo($commentOnLootItems);

        $officerRole = DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 6, 'is_visible' => true]);
        $officerRole->givePermissionTo($viewLootBiasTool);
        $officerRole->givePermissionTo($commentOnLootItems);
        $officerRole->givePermissionTo($editItems);
    }

    protected function createItem(): Item
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        return Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);
    }

    // ==========================================
    // Comment visibility tests
    // ==========================================

    #[Test]
    public function new_comment_appears_after_creation(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        // First request shows no comments
        $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));

        // Create a new comment
        $this->actingAs($user)->post(route('loot.items.comments.store', $item), [
            'body' => 'Brand new comment',
        ]);

        // Next request should show the new comment
        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));

        $response->assertOk();
        $response->assertSee('Brand new comment');
    }

    #[Test]
    public function updated_comment_appears_after_update(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'body' => 'Original comment body',
        ]);

        // First request populates the page
        $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));

        // Update the comment
        $this->actingAs($user)->put(route('loot.comments.update', [$item, $comment]), [
            'body' => 'Updated comment body',
        ]);

        // Next request should show the updated comment
        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));

        $response->assertOk();
        $response->assertSee('Updated comment body');
    }

    #[Test]
    public function deleted_comment_disappears_after_deletion(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'body' => 'Comment to be deleted',
        ]);

        // First request shows the comment
        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));
        $response->assertSee('Comment to be deleted');

        // Delete the comment
        $this->actingAs($user)->delete(route('loot.comments.destroy', [$item, $comment]));

        // Next request should not show the deleted comment
        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));

        $response->assertOk();
        $response->assertDontSee('Comment to be deleted');
    }
}
