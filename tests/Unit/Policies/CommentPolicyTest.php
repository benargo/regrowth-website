<?php

namespace Tests\Unit\Policies;

use App\Models\Comment;
use App\Models\DiscordRole;
use App\Models\User;
use App\Policies\CommentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

#[Group('comments')]
#[Group('auth')]
class CommentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private CommentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new CommentPolicy;
    }

    private function userWithPermission(string $permission): User
    {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);

        $role = DiscordRole::factory()->create();
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->discordRoles()->attach($role->id);
        $user->load('discordRoles.permissions');

        return $user;
    }

    private function userWithoutPermission(): User
    {
        $user = User::factory()->create();
        $user->load('discordRoles.permissions');

        return $user;
    }

    // ==================== viewAny ====================

    #[Test]
    public function view_any_requires_view_all_comments(): void
    {
        $this->assertTrue($this->policy->viewAny($this->userWithPermission('view-all-comments')));
        $this->assertFalse($this->policy->viewAny($this->userWithoutPermission()));
    }

    // ==================== create ====================

    #[Test]
    public function create_requires_comment_on_loot_items(): void
    {
        $this->assertTrue($this->policy->create($this->userWithPermission('comment-on-loot-items')));
        $this->assertFalse($this->policy->create($this->userWithoutPermission()));
    }

    // ==================== update ====================

    #[Test]
    public function update_allows_the_author_of_an_unresolved_comment(): void
    {
        $author = $this->userWithoutPermission();
        $comment = Comment::factory()->create(['user_id' => $author->id, 'is_resolved' => false]);

        $this->assertTrue($this->policy->update($author, $comment));
    }

    #[Group('authorization')]
    #[Test]
    public function update_denies_a_non_author(): void
    {
        $author = $this->userWithoutPermission();
        $other = $this->userWithoutPermission();
        $comment = Comment::factory()->create(['user_id' => $author->id, 'is_resolved' => false]);

        $this->assertFalse($this->policy->update($other, $comment));
    }

    #[Group('authorization')]
    #[Test]
    public function update_denies_the_author_of_a_resolved_comment(): void
    {
        $author = $this->userWithoutPermission();
        $comment = Comment::factory()->resolved()->create(['user_id' => $author->id]);

        $this->assertFalse($this->policy->update($author, $comment));
    }

    #[Test]
    public function update_allows_a_holder_of_edit_any_comment_even_when_resolved(): void
    {
        $officer = $this->userWithPermission('edit-any-comment');
        $author = $this->userWithoutPermission();
        $comment = Comment::factory()->resolved()->create(['user_id' => $author->id]);

        $this->assertTrue($this->policy->update($officer, $comment));
    }

    // ==================== delete ====================

    #[Test]
    public function delete_allows_the_author(): void
    {
        $author = $this->userWithoutPermission();
        $comment = Comment::factory()->create(['user_id' => $author->id]);

        $this->assertTrue($this->policy->delete($author, $comment));
    }

    #[Group('authorization')]
    #[Test]
    public function delete_denies_a_non_author(): void
    {
        $author = $this->userWithoutPermission();
        $other = $this->userWithoutPermission();
        $comment = Comment::factory()->create(['user_id' => $author->id]);

        $this->assertFalse($this->policy->delete($other, $comment));
    }

    #[Test]
    public function delete_allows_a_holder_of_delete_any_comment(): void
    {
        $officer = $this->userWithPermission('delete-any-comment');
        $author = $this->userWithoutPermission();
        $comment = Comment::factory()->create(['user_id' => $author->id]);

        $this->assertTrue($this->policy->delete($officer, $comment));
    }

    // ==================== markAsResolved ====================

    #[Test]
    public function mark_as_resolved_requires_mark_comment_as_resolved(): void
    {
        $officer = $this->userWithPermission('mark-comment-as-resolved');
        $author = $this->userWithoutPermission();
        $comment = Comment::factory()->create(['user_id' => $author->id]);

        $this->assertTrue($this->policy->markAsResolved($officer, $comment));
    }

    #[Group('authorization')]
    #[Test]
    public function mark_as_resolved_denies_the_author_without_the_permission(): void
    {
        $author = $this->userWithoutPermission();
        $comment = Comment::factory()->create(['user_id' => $author->id]);

        $this->assertFalse($this->policy->markAsResolved($author, $comment));
    }
}
