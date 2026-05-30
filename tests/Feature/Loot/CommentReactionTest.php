<?php

namespace Tests\Feature\Loot;

use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use App\Models\Boss;
use App\Models\DiscordRole;
use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\CommentReaction;
use App\Models\LootCouncil\Item;
use App\Models\Permission;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use App\Services\Blizzard\BlizzardService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class CommentReactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockItemService();

        $viewLootBiasTool = Permission::firstOrCreate(['name' => 'view-loot-bias-tool', 'guard_name' => 'web']);
        $reactToComments = Permission::firstOrCreate(['name' => 'react-to-comments', 'guard_name' => 'web']);

        $officerRole = DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 6, 'is_visible' => true]);
        $officerRole->givePermissionTo($viewLootBiasTool);
        $officerRole->givePermissionTo($reactToComments);

        $raiderRole = DiscordRole::firstOrCreate(['id' => '1265247017215594496'], ['name' => 'Raider', 'position' => 4, 'is_visible' => true]);
        $raiderRole->givePermissionTo($viewLootBiasTool);
        $raiderRole->givePermissionTo($reactToComments);

        $memberRole = DiscordRole::firstOrCreate(['id' => '829022020301094922'], ['name' => 'Member', 'position' => 3, 'is_visible' => true]);
        $memberRole->givePermissionTo($viewLootBiasTool);
        $memberRole->givePermissionTo($reactToComments);
    }

    protected function mockItemService(): void
    {
        Storage::fake('public');

        $this->instance(
            BlizzardService::class,
            Mockery::mock(BlizzardService::class, function (MockInterface $mock) {
                $mock->shouldReceive('findItem')
                    ->andReturnUsing(fn (int $id) => [
                        'id' => $id,
                        'name' => "Test Item {$id}",
                    ]);
            })
        );

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600]),
            GetItemMediaRequest::class => MockResponse::make(body: ['id' => 0, 'assets' => []], status: 200),
            FetchAssetRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);
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

    #[Test]
    public function authenticated_users_can_react_to_comments_from_other_users(): void
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

    #[Test]
    public function users_cannot_react_to_their_own_comments(): void
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

    #[Test]
    public function unauthenticated_users_cannot_react_to_comments(): void
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

    #[Test]
    public function guest_users_cannot_react_to_comments(): void
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

    #[Test]
    public function member_users_can_react_to_comments(): void
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

    #[Test]
    public function officer_users_can_react_to_comments(): void
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
    // Model validation tests
    // ==========================================

    #[Test]
    public function model_prevents_user_from_reacting_to_own_comment_directly(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
        ]);

        $this->expectException(ValidationException::class);

        CommentReaction::create([
            'comment_id' => $comment->id,
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function user_cannot_react_to_same_comment_twice(): void
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

        $this->expectException(UniqueConstraintViolationException::class);

        CommentReaction::factory()->create([
            'comment_id' => $comment->id,
            'user_id' => $reactingUser->id,
        ]);
    }
}
