<?php

namespace Tests\Unit\Policies;

use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\User;
use App\Policies\CharacterPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

#[Group('auth')]
class CharacterPolicyTest extends TestCase
{
    use RefreshDatabase;

    private CharacterPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new CharacterPolicy;
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

    #[Test]
    public function it_allows_create_with_permission(): void
    {
        $user = $this->userWithPermission('create-characters');

        $this->assertTrue($this->policy->create($user));
    }

    #[Test]
    public function it_denies_create_without_permission(): void
    {
        $user = $this->userWithoutPermission();

        $this->assertFalse($this->policy->create($user));
    }

    #[Test]
    public function it_allows_update_with_permission(): void
    {
        $user = $this->userWithPermission('update-characters');
        $character = Character::factory()->create();

        $this->assertTrue($this->policy->update($user, $character));
    }

    #[Test]
    public function it_denies_update_without_permission(): void
    {
        $user = $this->userWithoutPermission();
        $character = Character::factory()->create();

        $this->assertFalse($this->policy->update($user, $character));
    }

    #[Test]
    public function it_allows_delete_with_permission(): void
    {
        $user = $this->userWithPermission('delete-characters');
        $character = Character::factory()->create();

        $this->assertTrue($this->policy->delete($user, $character));
    }

    #[Test]
    public function it_denies_delete_without_permission(): void
    {
        $user = $this->userWithoutPermission();
        $character = Character::factory()->create();

        $this->assertFalse($this->policy->delete($user, $character));
    }
}
