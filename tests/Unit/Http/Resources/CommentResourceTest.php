<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\CommentResource;
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
use Tests\TestCase;

#[Group('comments')]
class CommentResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->mockCacheService();
        $this->setUpPermissions();
    }

    #[Test]
    public function it_returns_id(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertSame($comment->id, $array['id']);
    }

    // ==================== body ====================

    #[Test]
    public function it_returns_body(): void
    {
        $comment = Comment::factory()->withBody('This is a test comment')->create();

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertSame('This is a test comment', $array['body']);
    }

    // ==================== commentable relation ====================

    #[Test]
    public function it_omits_commentable_when_not_loaded(): void
    {
        $item = Item::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
        ]);

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertArrayNotHasKey('commentable', $array);
    }

    #[Test]
    public function it_returns_full_commentable_data_when_loaded(): void
    {
        $item = Item::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
        ]);
        $comment->load('commentable');

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertIsArray($array['commentable']);
        $this->assertSame($item->id, $array['commentable']['id']);
        $this->assertArrayHasKey('name', $array['commentable']);
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
        $array = $resource->resolve(new Request);

        $this->assertNull($array['commentable']);
    }

    // ==================== user relation ====================

    #[Test]
    public function it_omits_user_when_not_loaded(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertArrayNotHasKey('user', $array);
    }

    #[Test]
    public function it_returns_full_user_data_when_user_is_loaded(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $comment = Comment::factory()->create(['user_id' => $user->id]);
        $comment->load('user');

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertIsArray($array['user']);
        $this->assertSame($user->id, $array['user']['id']);
        $this->assertSame('testuser', $array['user']['username']);
    }

    // ==================== timestamps ====================

    #[Test]
    public function it_returns_created_at_timestamp(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertEquals($comment->created_at, $array['created_at']);
    }

    #[Test]
    public function it_returns_updated_at_timestamp(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertEquals($comment->updated_at, $array['updated_at']);
    }

    // ==================== resolved state ====================

    #[Test]
    public function it_returns_is_resolved_false_by_default(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertFalse($array['is_resolved']);
    }

    #[Test]
    public function it_returns_is_resolved_true_when_resolved(): void
    {
        $comment = Comment::factory()->resolved()->create();

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertTrue($array['is_resolved']);
    }

    // ==================== edit and delete permissions ====================

    #[Test]
    #[Group('authorization')]
    public function it_returns_can_edit_false_for_guest_user(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertFalse($array['permissions']['edit']);
    }

    #[Test]
    #[Group('authorization')]
    public function it_returns_can_delete_false_for_guest_user(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertFalse($array['permissions']['delete']);
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
        $array = $resource->resolve($request);

        $this->assertTrue($array['permissions']['edit']);
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
        $array = $resource->resolve($request);

        $this->assertTrue($array['permissions']['edit']);
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
        $array = $resource->resolve($request);

        $this->assertTrue($array['permissions']['edit']);
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
        $array = $resource->resolve($request);

        $this->assertTrue($array['permissions']['delete']);
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
        $array = $resource->resolve($request);

        $this->assertTrue($array['permissions']['delete']);
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
        $array = $resource->resolve($request);

        $this->assertFalse($array['permissions']['delete']);
    }

    // ==================== resolve permission ====================

    #[Test]
    #[Group('authorization')]
    public function it_returns_can_resolve_false_for_guest_user(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertFalse($array['permissions']['resolve']);
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
        $array = $resource->resolve($request);

        $this->assertFalse($array['permissions']['resolve']);
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
        $array = $resource->resolve($request);

        $this->assertTrue($array['permissions']['resolve']);
    }

    // ==================== permissions structure and keys ====================

    #[Test]
    #[Group('resource')]
    public function it_returns_permissions_structure(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertArrayHasKey('permissions', $array);
        $this->assertArrayHasKey('edit', $array['permissions']);
        $this->assertArrayHasKey('delete', $array['permissions']);
        $this->assertArrayHasKey('resolve', $array['permissions']);
        $this->assertArrayHasKey('reply', $array['permissions']);
    }

    #[Test]
    #[Group('resource')]
    public function it_returns_all_expected_keys(): void
    {
        $comment = Comment::factory()->create();
        $comment->load(['user', 'commentable', 'reactions', 'replies']);

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('parent_id', $array);
        $this->assertArrayHasKey('body', $array);
        $this->assertArrayHasKey('commentable', $array);
        $this->assertArrayHasKey('user', $array);
        $this->assertArrayHasKey('reactions', $array);
        $this->assertArrayHasKey('replies', $array);
        $this->assertArrayHasKey('replies_count', $array);
        $this->assertArrayHasKey('is_resolved', $array);
        $this->assertArrayHasKey('is_deleted', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
        $this->assertArrayHasKey('permissions', $array);
    }

    // ==================== reactions ====================

    #[Test]
    public function it_omits_reactions_when_not_loaded(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertArrayNotHasKey('reactions', $array);
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
        $array = $resource->resolve(new Request);

        $this->assertIsArray($array['reactions']);
        $this->assertCount(1, $array['reactions']);
        $this->assertArrayHasKey('id', $array['reactions'][0]);
        $this->assertArrayHasKey('user', $array['reactions'][0]);
        $this->assertSame('reactor', $array['reactions'][0]['user']['username']);
    }

    #[Test]
    public function its_embedded_reactions_carry_the_comment_id(): void
    {
        $author = User::factory()->create();
        $reactor = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $author->id]);
        CommentReaction::factory()->forComment($comment)->byUser($reactor)->create();

        $comment->load('reactions.user');

        $array = (new CommentResource($comment))->resolve(new Request);

        $this->assertSame($comment->id, $array['reactions'][0]['comment_id']);
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
        $array = $resource->resolve(new Request);

        $this->assertCount(2, $array['reactions']);
    }

    // ==================== react permission ====================

    #[Test]
    #[Group('authorization')]
    public function it_returns_can_react_false_for_guest_user(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertFalse($array['permissions']['react']);
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
        $array = $resource->resolve($request);

        $this->assertFalse($array['permissions']['react']);
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
        $array = $resource->resolve($request);

        $this->assertTrue($array['permissions']['react']);
    }

    // ==================== permissions structure includes react and reply ====================

    #[Test]
    #[Group('resource')]
    public function it_returns_permissions_structure_includes_react(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertArrayHasKey('permissions', $array);
        $this->assertArrayHasKey('react', $array['permissions']);
    }

    #[Test]
    #[Group('resource')]
    public function it_returns_permissions_structure_includes_reply(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertArrayHasKey('permissions', $array);
        $this->assertArrayHasKey('reply', $array['permissions']);
    }

    // ==================== parent id ====================

    #[Test]
    public function it_returns_a_null_parent_id_for_a_root(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertNull($array['parent_id']);
    }

    #[Test]
    public function it_returns_the_parent_id_for_a_reply(): void
    {
        $root = Comment::factory()->create();
        $reply = Comment::factory()->replyTo($root)->create();

        $resource = new CommentResource($reply);
        $array = $resource->resolve(new Request);

        $this->assertEquals($root->id, $array['parent_id']);
    }

    // ==================== replies and replies count ====================

    #[Test]
    public function it_returns_the_replies_count_from_the_loaded_count(): void
    {
        $root = Comment::factory()->create();
        Comment::factory()->replyTo($root)->count(3)->create();

        $loaded = Comment::withCount('replies')->find($root->id);

        $resource = new CommentResource($loaded);
        $array = $resource->resolve(new Request);

        $this->assertSame(3, $array['replies_count']);
    }

    #[Test]
    public function it_returns_a_zero_replies_count_when_the_count_is_not_loaded(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertSame(0, $array['replies_count']);
    }

    #[Test]
    public function it_omits_replies_when_not_loaded(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertArrayNotHasKey('replies', $array);
    }

    #[Test]
    public function it_returns_loaded_replies_oldest_first(): void
    {
        $root = Comment::factory()->create();
        $older = Comment::factory()->replyTo($root)->create(['created_at' => now()->subHour()]);
        $newer = Comment::factory()->replyTo($root)->create(['created_at' => now()]);

        $loaded = Comment::with('replies')->find($root->id);

        $resource = new CommentResource($loaded);
        $array = $resource->resolve(new Request);

        $this->assertCount(2, $array['replies']);
        $this->assertEquals(
            [$older->id, $newer->id],
            array_column($array['replies'], 'id'),
        );
    }

    // ==================== soft deletion ====================

    #[Test]
    public function it_reports_a_live_comment_as_not_deleted(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertFalse($array['is_deleted']);
    }

    #[Test]
    public function it_masks_the_body_of_a_deleted_comment(): void
    {
        $comment = Comment::factory()->withBody('Secret body text.')->create();
        $comment->delete();

        $resource = new CommentResource($comment->fresh());
        $array = $resource->resolve(new Request);

        $this->assertTrue($array['is_deleted']);
        $this->assertNull($array['body']);
    }

    #[Test]
    #[Group('authorization')]
    public function it_forces_every_permission_false_on_a_deleted_comment(): void
    {
        $comment = Comment::factory()->create();
        $comment->delete();

        $resource = new CommentResource($comment->fresh());
        $array = $resource->resolve(new Request);

        $this->assertSame(
            ['edit' => false, 'delete' => false, 'react' => false, 'resolve' => false, 'reply' => false],
            $array['permissions'],
        );
    }

    #[Test]
    public function it_keeps_the_author_and_timestamp_on_a_deleted_comment(): void
    {
        $comment = Comment::factory()->create();
        $comment->load('user');
        $comment->delete();

        $resource = new CommentResource($comment->fresh()->load('user'));
        $array = $resource->resolve(new Request);

        $this->assertNotNull($array['user']);
        $this->assertNotNull($array['created_at']);
    }

    // ==================== reply permission ====================

    #[Test]
    #[Group('authorization')]
    public function it_returns_can_reply_true_for_a_permitted_user(): void
    {
        $raider = User::factory()->raider()->create();
        $discordRole = $raider->discordRoles()->create([
            'id' => '1265247017215594497',
            'name' => 'Test Reply Role',
            'position' => 1,
            'is_visible' => true,
        ]);
        $discordRole->permissions()->create([
            'name' => 'comment-on-loot-items',
            'guard_name' => 'web',
        ]);
        $comment = Comment::factory()->create();

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $raider);

        $resource = new CommentResource($comment);
        $array = $resource->resolve($request);

        $this->assertTrue($array['permissions']['reply']);
    }

    #[Test]
    #[Group('authorization')]
    public function it_returns_can_reply_false_for_guest_user(): void
    {
        $comment = Comment::factory()->create();

        $resource = new CommentResource($comment);
        $array = $resource->resolve(new Request);

        $this->assertFalse($array['permissions']['reply']);
    }

    // ==================== helpers ====================

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
