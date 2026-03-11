<?php

namespace Tests\Feature\Api\Discord;

use App\Models\User;
use App\Services\Discord\DiscordGuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GuildResourceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * @return array{user: array{id: string, username: string}, nick: string|null, avatar: null, banner: null, roles: array, joined_at: string, deaf: bool, mute: bool}
     */
    private function fakeGuildMember(string $userId, string $username, ?string $nickname = null): array
    {
        return [
            'user' => ['id' => $userId, 'username' => $username],
            'nick' => $nickname,
            'avatar' => null,
            'banner' => null,
            'roles' => [],
            'joined_at' => '2021-01-01T00:00:00.000000+00:00',
            'deaf' => false,
            'mute' => false,
        ];
    }

    // ==================== searchMembers: Validation ====================

    #[Test]
    public function search_members_returns_422_when_query_is_missing(): void
    {
        $user = User::factory()->withPermissions('create-planned-absences')->create();

        $response = $this->actingAs($user)
            ->getJson(route('api.discord.guild.members.search'));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['query']);
    }

    // ==================== searchMembers: Happy Paths ====================

    #[Test]
    public function search_members_returns_results_for_user_with_create_planned_absences_permission(): void
    {
        $user = User::factory()->withPermissions('create-planned-absences')->create();

        $this->mock(DiscordGuildService::class)
            ->shouldReceive('searchGuildMembers')
            ->once()
            ->with('test', 1)
            ->andReturn([$this->fakeGuildMember('111111111111111111', 'testuser', 'TestNick')]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discord.guild.members.search', ['query' => 'test']));

        $response->assertOk()
            ->assertJson([
                ['id' => '111111111111111111', 'nickname' => 'TestNick', 'username' => 'testuser'],
            ]);
    }

    #[Test]
    public function search_members_returns_results_for_user_with_create_planned_absences_for_others_permission(): void
    {
        $user = User::factory()->withPermissions('manage-planned-absences')->create();

        $this->mock(DiscordGuildService::class)
            ->shouldReceive('searchGuildMembers')
            ->once()
            ->with('officer', 1)
            ->andReturn([$this->fakeGuildMember('222222222222222222', 'guildofficer', 'Guild Officer')]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discord.guild.members.search', ['query' => 'officer']));

        $response->assertOk()
            ->assertJson([
                ['id' => '222222222222222222', 'nickname' => 'Guild Officer', 'username' => 'guildofficer'],
            ]);
    }

    #[Test]
    public function search_members_returns_null_nickname_when_member_has_no_nickname(): void
    {
        $user = User::factory()->withPermissions('create-planned-absences')->create();

        $this->mock(DiscordGuildService::class)
            ->shouldReceive('searchGuildMembers')
            ->once()
            ->andReturn([$this->fakeGuildMember('333333333333333333', 'nonick')]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discord.guild.members.search', ['query' => 'nonick']));

        $response->assertOk()
            ->assertJson([
                ['id' => '333333333333333333', 'nickname' => null, 'username' => 'nonick'],
            ]);
    }

    #[Test]
    public function search_members_uses_custom_limit_when_provided(): void
    {
        $user = User::factory()->withPermissions('create-planned-absences')->create();

        $this->mock(DiscordGuildService::class)
            ->shouldReceive('searchGuildMembers')
            ->once()
            ->with('test', 10)
            ->andReturn([]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discord.guild.members.search', ['query' => 'test', 'limit' => 10]));

        $response->assertOk()->assertJson([]);
    }

    #[Test]
    public function search_members_caches_results_for_subsequent_requests(): void
    {
        $user = User::factory()->withPermissions('create-planned-absences')->create();

        $this->mock(DiscordGuildService::class)
            ->shouldReceive('searchGuildMembers')
            ->once() // Only called once despite two requests
            ->andReturn([$this->fakeGuildMember('444444444444444444', 'cacheduser', 'Cached')]);

        $this->actingAs($user)
            ->getJson(route('api.discord.guild.members.search', ['query' => 'cach']));

        $this->actingAs($user)
            ->getJson(route('api.discord.guild.members.search', ['query' => 'cach']))
            ->assertOk()
            ->assertJson([
                ['id' => '444444444444444444', 'nickname' => 'Cached', 'username' => 'cacheduser'],
            ]);
    }

    #[Test]
    public function search_members_returns_empty_array_when_no_matches_found(): void
    {
        $user = User::factory()->withPermissions('create-planned-absences')->create();

        $this->mock(DiscordGuildService::class)
            ->shouldReceive('searchGuildMembers')
            ->once()
            ->andReturn([]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discord.guild.members.search', ['query' => 'zzznobodymatchesthis']));

        $response->assertOk()->assertJson([]);
    }
}
