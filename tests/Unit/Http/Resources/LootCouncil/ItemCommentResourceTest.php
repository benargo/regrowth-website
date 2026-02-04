<?php

namespace Tests\Unit\Http\Resources\LootCouncil;

use App\Http\Resources\LootCouncil\ItemCommentResource;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\ItemComment;
use App\Models\User;
use App\Services\Blizzard\ItemService;
use App\Services\Blizzard\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemCommentResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockBlizzardServices();
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

    #[Test]
    public function it_returns_id(): void
    {
        $comment = ItemComment::factory()->create();

        $resource = new ItemCommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertSame($comment->id, $array['id']);
    }

    #[Test]
    public function it_returns_body(): void
    {
        $comment = ItemComment::factory()->withBody('This is a test comment')->create();

        $resource = new ItemCommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertSame('This is a test comment', $array['body']);
    }

    #[Test]
    public function it_returns_item_id_when_item_not_loaded(): void
    {
        $item = Item::factory()->create();
        $comment = ItemComment::factory()->create(['item_id' => $item->id]);

        $resource = new ItemCommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertSame($item->id, $array['item']);
    }

    #[Test]
    public function it_returns_full_item_data_when_item_is_loaded(): void
    {
        $item = Item::factory()->create();
        $comment = ItemComment::factory()->create(['item_id' => $item->id]);
        $comment->load('item');

        $resource = new ItemCommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertIsArray($array['item']);
        $this->assertSame($item->id, $array['item']['id']);
        $this->assertArrayHasKey('name', $array['item']);
    }

    #[Test]
    public function it_returns_user_id_when_user_not_loaded(): void
    {
        $user = User::factory()->create();
        $comment = ItemComment::factory()->create(['user_id' => $user->id]);

        $resource = new ItemCommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertSame($user->id, $array['user']);
    }

    #[Test]
    public function it_returns_full_user_data_when_user_is_loaded(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $comment = ItemComment::factory()->create(['user_id' => $user->id]);
        $comment->load('user');

        $resource = new ItemCommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertIsArray($array['user']);
        $this->assertSame($user->id, $array['user']['id']);
        $this->assertSame('testuser', $array['user']['username']);
    }

    #[Test]
    public function it_returns_created_at_timestamp(): void
    {
        $comment = ItemComment::factory()->create();

        $resource = new ItemCommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertEquals($comment->created_at, $array['created_at']);
    }

    #[Test]
    public function it_returns_updated_at_timestamp(): void
    {
        $comment = ItemComment::factory()->create();

        $resource = new ItemCommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertEquals($comment->updated_at, $array['updated_at']);
    }

    #[Test]
    public function it_returns_can_edit_false_for_guest_user(): void
    {
        $comment = ItemComment::factory()->create();

        $resource = new ItemCommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertFalse($array['can']['edit']);
    }

    #[Test]
    public function it_returns_can_delete_false_for_guest_user(): void
    {
        $comment = ItemComment::factory()->create();

        $resource = new ItemCommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertFalse($array['can']['delete']);
    }

    #[Test]
    public function it_returns_can_edit_true_for_comment_owner_who_is_officer(): void
    {
        $user = User::factory()->officer()->create();
        $comment = ItemComment::factory()->create(['user_id' => $user->id]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);

        $resource = new ItemCommentResource($comment);
        $array = $resource->toArray($request);

        $this->assertTrue($array['can']['edit']);
    }

    #[Test]
    public function it_returns_can_edit_true_for_comment_owner_who_is_raider(): void
    {
        $user = User::factory()->raider()->create();
        $comment = ItemComment::factory()->create(['user_id' => $user->id]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);

        $resource = new ItemCommentResource($comment);
        $array = $resource->toArray($request);

        $this->assertTrue($array['can']['edit']);
    }

    #[Test]
    public function it_returns_can_edit_true_for_officer_who_did_not_create_comment(): void
    {
        $officer = User::factory()->officer()->create();
        $otherUser = User::factory()->create();
        $comment = ItemComment::factory()->create(['user_id' => $otherUser->id]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $officer);

        $resource = new ItemCommentResource($comment);
        $array = $resource->toArray($request);

        $this->assertTrue($array['can']['edit']);
    }

    #[Test]
    public function it_returns_can_delete_true_for_officer(): void
    {
        $officer = User::factory()->officer()->create();
        $otherUser = User::factory()->create();
        $comment = ItemComment::factory()->create(['user_id' => $otherUser->id]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $officer);

        $resource = new ItemCommentResource($comment);
        $array = $resource->toArray($request);

        $this->assertTrue($array['can']['delete']);
    }

    #[Test]
    public function it_returns_can_delete_true_for_comment_owner_who_is_raider(): void
    {
        $user = User::factory()->raider()->create();
        $comment = ItemComment::factory()->create(['user_id' => $user->id]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);

        $resource = new ItemCommentResource($comment);
        $array = $resource->toArray($request);

        $this->assertTrue($array['can']['delete']);
    }

    #[Test]
    public function it_returns_can_delete_false_for_raider_who_did_not_create_comment(): void
    {
        $raider = User::factory()->raider()->create();
        $otherUser = User::factory()->create();
        $comment = ItemComment::factory()->create(['user_id' => $otherUser->id]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $raider);

        $resource = new ItemCommentResource($comment);
        $array = $resource->toArray($request);

        $this->assertFalse($array['can']['delete']);
    }

    #[Test]
    public function it_returns_can_permissions_structure(): void
    {
        $comment = ItemComment::factory()->create();

        $resource = new ItemCommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertArrayHasKey('can', $array);
        $this->assertArrayHasKey('edit', $array['can']);
        $this->assertArrayHasKey('delete', $array['can']);
    }

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $comment = ItemComment::factory()->create();

        $resource = new ItemCommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('body', $array);
        $this->assertArrayHasKey('item', $array);
        $this->assertArrayHasKey('user', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
        $this->assertArrayHasKey('can', $array);
    }
}
