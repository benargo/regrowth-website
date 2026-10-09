<?php

namespace Tests\Unit\Policies;

use App\Models\DiscordRole;
use App\Models\User;
use App\Models\WarcraftLogs\Guild;
use App\Models\WarcraftLogs\GuildTag;
use App\Policies\WarcraftLogsGuildPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

#[Group('auth')]
class WarcraftLogsGuildPolicyTest extends TestCase
{
    use RefreshDatabase;

    private WarcraftLogsGuildPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new WarcraftLogsGuildPolicy;
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
    public function it_allows_view_any_with_view_warcraft_logs_guilds_permission(): void
    {
        $user = $this->userWithPermission('view-warcraft-logs-guilds');

        $this->assertTrue($this->policy->viewAny($user));
    }

    #[Test]
    public function it_denies_view_any_without_permission(): void
    {
        $this->assertFalse($this->policy->viewAny($this->userWithoutPermission()));
    }

    // ==================== view ====================

    #[Test]
    public function it_allows_view_with_view_warcraft_logs_guilds_permission(): void
    {
        $user = $this->userWithPermission('view-warcraft-logs-guilds');

        $this->assertTrue($this->policy->view($user, Guild::factory()->make()));
    }

    #[Test]
    public function it_denies_view_without_permission(): void
    {
        $this->assertFalse($this->policy->view($this->userWithoutPermission(), Guild::factory()->make()));
    }

    // ==================== create ====================

    #[Test]
    public function it_allows_create_with_create_warcraft_logs_guilds_permission(): void
    {
        $user = $this->userWithPermission('create-warcraft-logs-guilds');

        $this->assertTrue($this->policy->create($user));
    }

    #[Test]
    public function it_denies_create_without_permission(): void
    {
        $this->assertFalse($this->policy->create($this->userWithoutPermission()));
    }

    // ==================== update ====================

    #[Test]
    public function it_allows_guild_update_with_update_warcraft_logs_guilds_permission(): void
    {
        $user = $this->userWithPermission('update-warcraft-logs-guilds');

        $this->assertTrue($this->policy->update($user, Guild::factory()->make()));
    }

    #[Test]
    public function it_denies_guild_update_with_only_update_warcraft_logs_tags_permission(): void
    {
        $user = $this->userWithPermission('update-warcraft-logs-tags');

        $this->assertFalse($this->policy->update($user, Guild::factory()->make()));
    }

    #[Test]
    public function it_allows_tag_update_with_update_warcraft_logs_tags_permission(): void
    {
        $user = $this->userWithPermission('update-warcraft-logs-tags');

        $this->assertTrue($this->policy->update($user, GuildTag::factory()->make()));
    }

    #[Test]
    public function it_denies_tag_update_with_only_update_warcraft_logs_guilds_permission(): void
    {
        $user = $this->userWithPermission('update-warcraft-logs-guilds');

        $this->assertFalse($this->policy->update($user, GuildTag::factory()->make()));
    }

    #[Test]
    public function it_denies_update_without_permission(): void
    {
        $user = $this->userWithoutPermission();

        $this->assertFalse($this->policy->update($user, Guild::factory()->make()));
        $this->assertFalse($this->policy->update($user, GuildTag::factory()->make()));
    }

    // ==================== delete ====================

    #[Test]
    public function it_allows_delete_with_delete_warcraft_logs_guilds_permission(): void
    {
        $user = $this->userWithPermission('delete-warcraft-logs-guilds');

        $this->assertTrue($this->policy->delete($user, Guild::factory()->make()));
    }

    #[Test]
    public function it_denies_delete_without_permission(): void
    {
        $this->assertFalse($this->policy->delete($this->userWithoutPermission(), Guild::factory()->make()));
    }
}
