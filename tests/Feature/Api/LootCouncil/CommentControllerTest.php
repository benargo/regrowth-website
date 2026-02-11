<?php

namespace Tests\Feature\Api\LootCouncil;

use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
use App\Models\TBC\Boss;
use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function createItem(): Item
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        return Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);
    }

    // ==========================================
    // Authentication tests
    // ==========================================

    public function test_resolve_requires_valid_bearer_token(): void
    {
        $comment = Comment::factory()->create();

        $response = $this->postJson(route('api.loot.comments.resolve', $comment));

        $response->assertForbidden();
    }

    public function test_resolve_rejects_invalid_bearer_token(): void
    {
        $comment = Comment::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->postJson(route('api.loot.comments.resolve', $comment));

        $response->assertForbidden();
    }

    // ==========================================
    // Resolve tests
    // ==========================================

    public function test_resolve_creates_new_resolved_comment_and_soft_deletes_original(): void
    {
        config(['services.discord.token' => 'test-bot-token']);

        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'body' => 'Comment to resolve',
            'is_resolved' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer test-bot-token')
            ->postJson(route('api.loot.comments.resolve', $comment));

        $response->assertOk();

        // Original comment should be soft deleted
        $this->assertSoftDeleted('lootcouncil_comments', [
            'id' => $comment->id,
            'body' => 'Comment to resolve',
        ]);

        // New comment should exist and be resolved
        $this->assertDatabaseHas('lootcouncil_comments', [
            'item_id' => $item->id,
            'user_id' => $user->id,
            'body' => 'Comment to resolve',
            'is_resolved' => true,
        ]);
    }

    public function test_resolve_preserves_original_created_at(): void
    {
        config(['services.discord.token' => 'test-bot-token']);

        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $originalTime = now()->subDays(3);
        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'created_at' => $originalTime,
        ]);

        $this->withHeader('Authorization', 'Bearer test-bot-token')
            ->postJson(route('api.loot.comments.resolve', $comment));

        $newComment = Comment::where('item_id', $item->id)
            ->where('is_resolved', true)
            ->whereNull('deleted_at')
            ->first();

        $this->assertEquals(
            $originalTime->format('Y-m-d H:i:s'),
            $newComment->created_at->format('Y-m-d H:i:s')
        );
    }

    public function test_resolve_sets_deleted_by_to_null(): void
    {
        config(['services.discord.token' => 'test-bot-token']);

        $comment = Comment::factory()->create(['is_resolved' => false]);

        $this->withHeader('Authorization', 'Bearer test-bot-token')
            ->postJson(route('api.loot.comments.resolve', $comment));

        $this->assertDatabaseHas('lootcouncil_comments', [
            'id' => $comment->id,
            'deleted_by' => null,
        ]);
    }

    public function test_resolve_returns_new_comment_in_response(): void
    {
        config(['services.discord.token' => 'test-bot-token']);

        $comment = Comment::factory()->create([
            'body' => 'Resolve me',
            'is_resolved' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer test-bot-token')
            ->postJson(route('api.loot.comments.resolve', $comment));

        $response->assertOk();
        $response->assertJsonFragment([
            'body' => 'Resolve me',
            'is_resolved' => true,
        ]);
    }
}
