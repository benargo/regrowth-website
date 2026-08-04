<?php

namespace Tests\Unit\Http\Resources\LootCouncil;

use App\Http\Resources\LootCouncil\CommentResource;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\DiscordRole;
use App\Models\Item;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('comments')]
class CommentResourceTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->mockItemService();
        $this->mockCacheService();
        $this->setUpPermissions();
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
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
        ]);

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertSame($item->id, $array['item']);
    }

    #[Test]
    public function it_returns_full_item_data_when_item_is_loaded(): void
    {
        $item = Item::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
        ]);
        $comment->load('commentable');

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertIsArray($array['item']);
        $this->assertSame($item->id, $array['item']['id']);
        $this->assertArrayHasKey('name', $array['item']);
    }

    #[Test]
    public function it_returns_null_when_commentable_is_loaded_but_model_has_been_deleted(): void
    {
        $item = Item::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
        ]);

        $item->forceDelete();
        $comment->load('commentable');

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertNull($array['item']);
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
    #[Group('authorization')]
    public function it_returns_can_edit_false_for_guest_user(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertFalse($array['can']['edit']);
    }

    #[Test]
    #[Group('authorization')]
    public function it_returns_can_delete_false_for_guest_user(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertFalse($array['can']['delete']);
    }

    #[Test]
    #[Group('authorization')]
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
    #[Group('authorization')]
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
    #[Group('authorization')]
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
    #[Group('authorization')]
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
    #[Group('authorization')]
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
    #[Group('authorization')]
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
    #[Group('authorization')]
    public function it_returns_can_resolve_false_for_guest_user(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertFalse($array['can']['resolve']);
    }

    #[Test]
    #[Group('authorization')]
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
    #[Group('authorization')]
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
    #[Group('resource')]
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
    #[Group('resource')]
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
    #[Group('authorization')]
    public function it_returns_can_react_false_for_guest_user(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertFalse($array['can']['react']);
    }

    #[Test]
    #[Group('authorization')]
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
    #[Group('authorization')]
    public function it_returns_can_react_true_for_non_owner(): void
    {
        $commentOwner = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $commentOwner->id]);

        $otherUser = User::factory()->create();
        $discordRole = $otherUser->discordRoles()->create([
            'id' => '1234567890',
            'name' => 'Test Role',
            'position' => 1,
            'is_visible' => true,
        ]);
        $discordRole->permissions()->create([
            'name' => 'react-to-comments',
            'guard_name' => 'web',
        ]);

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $otherUser);

        $resource = new CommentResource($comment);
        $array = $resource->toArray($request);

        $this->assertTrue($array['can']['react']);
    }

    #[Test]
    #[Group('resource')]
    public function it_returns_can_permissions_structure_includes_react(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->toArray(new Request);

        $this->assertArrayHasKey('can', $array);
        $this->assertArrayHasKey('react', $array['can']);
    }

    private function mockCacheService(): void
    {
        $realStore = Cache::store();

        $blizzardAuthStore = Mockery::mock();
        $blizzardAuthStore->shouldReceive('get')->andReturn(null);
        $blizzardAuthStore->shouldReceive('put')->andReturn(true);

        Cache::shouldReceive('tags')
            ->with(['blizzard', 'api-auth'])
            ->andReturn($blizzardAuthStore);

        Cache::shouldReceive('store')
            ->andReturn($realStore);
    }

    private function setUpPermissions(): void
    {
        $officerRole = DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 6, 'is_visible' => true]);
        $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'edit-any-comment', 'guard_name' => 'web']));
        $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'delete-any-comment', 'guard_name' => 'web']));
        $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'mark-comment-as-resolved', 'guard_name' => 'web']));
    }
}
