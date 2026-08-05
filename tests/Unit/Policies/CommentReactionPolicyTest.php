<?php

namespace Tests\Unit\Policies;

use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\DiscordRole;
use App\Models\User;
use App\Policies\CommentReactionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

#[Group('comments')]
#[Group('auth')]
class CommentReactionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private CommentReactionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new CommentReactionPolicy;
    }

    #[Test]
    public function create_allows_a_permitted_user_on_someone_elses_comment(): void
    {
        Permission::firstOrCreate(['name' => 'react-to-comments', 'guard_name' => 'web']);

        $role = DiscordRole::factory()->create();
        $role->givePermissionTo('react-to-comments');

        $author = User::factory()->create();
        $reactor = User::factory()->create();
        $reactor->discordRoles()->attach($role->id);
        $reactor->load('discordRoles.permissions');

        $comment = Comment::factory()->create(['user_id' => $author->id]);

        $this->assertTrue($this->policy->create($reactor, $comment));
    }

    #[Group('authorization')]
    #[Test]
    public function create_denies_reacting_to_your_own_comment(): void
    {
        Permission::firstOrCreate(['name' => 'react-to-comments', 'guard_name' => 'web']);

        $role = DiscordRole::factory()->create();
        $role->givePermissionTo('react-to-comments');

        $author = User::factory()->create();
        $author->discordRoles()->attach($role->id);
        $author->load('discordRoles.permissions');

        $comment = Comment::factory()->create(['user_id' => $author->id]);

        $this->assertFalse($this->policy->create($author, $comment));
    }

    #[Group('authorization')]
    #[Test]
    public function create_denies_a_user_without_the_permission(): void
    {
        $user = User::factory()->create();
        $author = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $author->id]);

        $this->assertFalse($this->policy->create($user, $comment));
    }

    #[Test]
    public function delete_allows_the_reactions_owner(): void
    {
        $author = User::factory()->create();
        $reactor = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $author->id]);
        $reaction = CommentReaction::factory()->forComment($comment)->byUser($reactor)->create();

        $this->assertTrue($this->policy->delete($reactor, $reaction));
    }

    #[Group('authorization')]
    #[Test]
    public function delete_denies_a_different_user(): void
    {
        $author = User::factory()->create();
        $reactor = User::factory()->create();
        $someoneElse = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $author->id]);
        $reaction = CommentReaction::factory()->forComment($comment)->byUser($reactor)->create();

        $this->assertFalse($this->policy->delete($someoneElse, $reaction));
    }

    #[Group('authorization')]
    #[Test]
    public function delete_denies_a_user_who_merely_holds_react_to_comments(): void
    {
        Permission::firstOrCreate(['name' => 'react-to-comments', 'guard_name' => 'web']);

        $role = DiscordRole::factory()->create();
        $role->givePermissionTo('react-to-comments');

        $author = User::factory()->create();
        $reactor = User::factory()->create();
        $interloper = User::factory()->create();
        $interloper->discordRoles()->attach($role->id);
        $interloper->load('discordRoles.permissions');

        $comment = Comment::factory()->create(['user_id' => $author->id]);
        $reaction = CommentReaction::factory()->forComment($comment)->byUser($reactor)->create();

        $this->assertTrue($interloper->isAuthorizedTo('react-to-comments'));
        $this->assertFalse($this->policy->delete($interloper, $reaction));
    }
}
