<?php

namespace Tests\Feature\Loot;

use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use App\Models\Boss;
use App\Models\DiscordRole;
use App\Models\Item;
use App\Models\LootCouncil\Comment;
use App\Models\Permission;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\Channel as DiscordChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;
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

        Storage::fake('public');

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600]),
            GetItemRequest::class => function (PendingRequest $pendingRequest): MockResponse {
                $path = parse_url($pendingRequest->getUrl(), PHP_URL_PATH) ?: '';
                $segments = explode('/', trim($path, '/'));
                $itemId = (int) ($segments[array_key_last($segments)] ?? 0);

                return MockResponse::make(body: [
                    'id' => $itemId,
                    'name' => "Test Item {$itemId}",
                    'quality' => ['type' => 'UNCOMMON', 'name' => 'Uncommon'],
                    'level' => 1,
                    'required_level' => 1,
                    'media' => ['key' => ['href' => "https://example.test/media/{$itemId}"]],
                    'item_class' => ['key' => ['href' => 'https://example.test/item-class/2'], 'name' => 'Weapon', 'id' => 2],
                    'item_subclass' => ['key' => ['href' => 'https://example.test/item-subclass/2-7'], 'name' => 'Sword', 'id' => 7],
                    'inventory_type' => ['type' => 'WEAPONMAINHAND', 'name' => 'Main Hand'],
                    'purchase_price' => 0,
                    'sell_price' => 0,
                ], status: 200);
            },
            GetItemMediaRequest::class => MockResponse::make(body: ['id' => 0, 'assets' => []], status: 200),
            FetchAssetRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

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
