<?php

namespace Tests\Unit\Http\Resources\LootCouncil;

use App\Http\Resources\LootCouncil\CommentResource;
use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\CommentReaction;
use App\Models\LootCouncil\Item;
use App\Models\User;
use App\Services\Blizzard\ItemService;
use App\Services\Blizzard\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommentResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->mockBlizzardServices();
        $this->mockCacheService();
    }

    protected function mockBlizzardServices(): void
    {
        $itemService = Mockery::mock(ItemService::class);
        $itemService->shouldReceive('find')->andReturn([
            'name' => 'Test Item',
            'item_class' => ['name' => 'Armor'],
            'item_subclass' => ['name' => 'Plate'],
            'quality' => ['type' => 'EPIC', 'name' => 'Epic'],
            'inventory_type' => ['name' => 'Head'],
        ]);

        $mediaService = Mockery::mock(MediaService::class);
        $mediaService->shouldReceive('find')->andReturn([
            'assets' => [
                ['key' => 'icon', 'value' => 'https://example.com/icon.jpg', 'file_data_id' => 12345],
            ],
        ]);
        $mediaService->shouldReceive('getAssetUrls')->andReturn([12345 => 'https://example.com/icon.jpg']);

        $this->app->instance(ItemService::class, $itemService);
        $this->app->instance(MediaService::class, $mediaService);
    }

    protected function mockCacheService(): void
    {
        $cacheStore = Mockery::mock();
        $cacheStore->shouldReceive('remember')
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());
        $cacheStore->shouldReceive('flush')->andReturn(true);

        Cache::shouldReceive('tags')
            ->with(['lootcouncil'])
            ->andReturn($cacheStore);
    }

    #[Test]
    public function it_returns_id(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertSame($comment->id, $array['id']);
    }

    #[Test]
    public function it_returns_body(): void
    {
        $comment = Comment::factory()->withBody('This is a test comment')->create();

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertSame('This is a test comment', $array['body']);
    }

    #[Test]
    public function it_returns_item_id_when_item_not_loaded(): void
    {
        $item = Item::factory()->create();
        $comment = Comment::factory()->create(['item_id' => $item->id]);

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertSame($item->id, $array['item']);
    }

    #[Test]
    public function it_returns_full_item_data_when_item_is_loaded(): void
    {
        $item = Item::factory()->create();
        $comment = Comment::factory()->create(['item_id' => $item->id]);
        $comment->load('item');

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertIsArray($array['item']);
        $this->assertSame($item->id, $array['item']['id']);
        $this->assertArrayHasKey('name', $array['item']);
    }

    #[Test]
    public function it_returns_user_id_when_user_not_loaded(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertSame($user->id, $array['user']);
    }

    #[Test]
    public function it_returns_full_user_data_when_user_is_loaded(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $comment = Comment::factory()->create(['user_id' => $user->id]);
        $comment->load('user');

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertIsArray($array['user']);
        $this->assertSame($user->id, $array['user']['id']);
        $this->assertSame('testuser', $array['user']['username']);
    }

    #[Test]
    public function it_returns_created_at_timestamp(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertEquals($comment->created_at, $array['created_at']);
    }

    #[Test]
    public function it_returns_updated_at_timestamp(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertEquals($comment->updated_at, $array['updated_at']);
    }

    #[Test]
    public function it_returns_is_resolved_false_by_default(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertFalse($array['is_resolved']);
    }

    #[Test]
    public function it_returns_is_resolved_true_when_resolved(): void
    {
        $comment = Comment::factory()->resolved()->create();

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertTrue($array['is_resolved']);
    }

    #[Test]
    public function it_returns_can_edit_false_for_guest_user(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertFalse($array['can']['edit']);
    }

    #[Test]
    public function it_returns_can_delete_false_for_guest_user(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertFalse($array['can']['delete']);
    }

    #[Test]
    public function it_returns_can_edit_true_for_comment_owner_who_is_officer(): void
    {
        $user = User::factory()->officer()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);

        $resource = new CommentResource($comment);
        $array = $resource->toArray($request);

        $this->assertTrue($array['can']['edit']);
    }

    #[Test]
    public function it_returns_can_edit_true_for_comment_owner_who_is_raider(): void
    {
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);

        $resource = new CommentResource($comment);
        $array = $resource->toArray($request);

        $this->assertTrue($array['can']['edit']);
    }

    #[Test]
    public function it_returns_can_edit_true_for_officer_who_did_not_create_comment(): void
    {
        $officer = User::factory()->officer()->create();
        $otherUser = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $otherUser->id]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $officer);

        $resource = new CommentResource($comment);
        $array = $resource->toArray($request);

        $this->assertTrue($array['can']['edit']);
    }

    #[Test]
    public function it_returns_can_delete_true_for_officer(): void
    {
        $officer = User::factory()->officer()->create();
        $otherUser = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $otherUser->id]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $officer);

        $resource = new CommentResource($comment);
        $array = $resource->toArray($request);

        $this->assertTrue($array['can']['delete']);
    }

    #[Test]
    public function it_returns_can_delete_true_for_comment_owner_who_is_raider(): void
    {
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);

        $resource = new CommentResource($comment);
        $array = $resource->toArray($request);

        $this->assertTrue($array['can']['delete']);
    }

    #[Test]
    public function it_returns_can_delete_false_for_raider_who_did_not_create_comment(): void
    {
        $raider = User::factory()->raider()->create();
        $otherUser = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $otherUser->id]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $raider);

        $resource = new CommentResource($comment);
        $array = $resource->toArray($request);

        $this->assertFalse($array['can']['delete']);
    }

    #[Test]
    public function it_returns_can_resolve_false_for_guest_user(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertFalse($array['can']['resolve']);
    }

    #[Test]
    public function it_returns_can_resolve_false_for_raider(): void
    {
        $raider = User::factory()->raider()->create();
        $comment = Comment::factory()->create();

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $raider);

        $resource = new CommentResource($comment);
        $array = $resource->toArray($request);

        $this->assertFalse($array['can']['resolve']);
    }

    #[Test]
    public function it_returns_can_resolve_true_for_officer(): void
    {
        $officer = User::factory()->officer()->create();
        $comment = Comment::factory()->create();

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $officer);

        $resource = new CommentResource($comment);
        $array = $resource->toArray($request);

        $this->assertTrue($array['can']['resolve']);
    }

    #[Test]
    public function it_returns_can_permissions_structure(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertArrayHasKey('can', $array);
        $this->assertArrayHasKey('edit', $array['can']);
        $this->assertArrayHasKey('delete', $array['can']);
        $this->assertArrayHasKey('resolve', $array['can']);
    }

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('body', $array);
        $this->assertArrayHasKey('item', $array);
        $this->assertArrayHasKey('user', $array);
        $this->assertArrayHasKey('reactions', $array);
        $this->assertArrayHasKey('is_resolved', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
        $this->assertArrayHasKey('can', $array);
    }

    #[Test]
    public function it_returns_empty_reactions_when_no_reactions_exist(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertIsArray($array['reactions']);
        $this->assertEmpty($array['reactions']);
    }

    #[Test]
    public function it_returns_reactions_with_user_data(): void
    {
        $commentOwner = User::factory()->create();
        $reactingUser = User::factory()->create(['username' => 'reactor']);
        $comment = Comment::factory()->create(['user_id' => $commentOwner->id]);
        CommentReaction::factory()->forComment($comment)->byUser($reactingUser)->create();

        $comment->load('reactions.user');

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertIsArray($array['reactions']);
        $this->assertCount(1, $array['reactions']);
        $this->assertArrayHasKey('id', $array['reactions'][0]);
        $this->assertArrayHasKey('user', $array['reactions'][0]);
        $this->assertSame('reactor', $array['reactions'][0]['user']['username']);
    }

    #[Test]
    public function it_returns_multiple_reactions(): void
    {
        $commentOwner = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $commentOwner->id]);

        $reactingUser1 = User::factory()->create();
        $reactingUser2 = User::factory()->create();
        CommentReaction::factory()->forComment($comment)->byUser($reactingUser1)->create();
        CommentReaction::factory()->forComment($comment)->byUser($reactingUser2)->create();

        $comment->load('reactions.user');

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertCount(2, $array['reactions']);
    }

    #[Test]
    public function it_returns_can_react_false_for_guest_user(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertFalse($array['can']['react']);
    }

    #[Test]
    public function it_returns_can_react_false_for_comment_owner(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);

        $resource = new CommentResource($comment);
        $array = $resource->toArray($request);

        $this->assertFalse($array['can']['react']);
    }

    #[Test]
    public function it_returns_can_react_true_for_non_owner(): void
    {
        $commentOwner = User::factory()->create();
        $otherUser = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $commentOwner->id]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $otherUser);

        $resource = new CommentResource($comment);
        $array = $resource->toArray($request);

        $this->assertTrue($array['can']['react']);
    }

    #[Test]
    public function it_returns_can_permissions_structure_includes_react(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertArrayHasKey('can', $array);
        $this->assertArrayHasKey('react', $array['can']);
    }
}
