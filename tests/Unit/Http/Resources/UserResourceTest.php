<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\DiscordRoleResource;
use App\Http\Resources\UserResource;
use App\Models\DiscordRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('auth')]
class UserResourceTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.discord.server_id', '123456789');
    }

    #[Test]
    public function it_returns_exactly_the_expected_keys_when_no_relations_are_loaded(): void
    {
        $user = User::factory()->create();

        $array = (new UserResource($user))->resolve(new Request);

        $this->assertSame(
            ['id', 'username', 'display_name', 'avatar', 'highest_role', 'admin'],
            array_keys($array),
        );
    }

    // ==================== id ====================

    #[Test]
    public function it_returns_id(): void
    {
        $user = User::factory()->create();

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame($user->id, $array['id']);
    }

    // ==================== username, discriminator, and nickname ====================

    #[Test]
    public function it_returns_username(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('testuser', $array['username']);
    }

    // ==================== display name ====================

    #[Test]
    public function it_returns_display_name_as_nickname_when_set(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'nickname' => 'TestNick',
        ]);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('TestNick', $array['display_name']);
    }

    #[Test]
    public function it_returns_display_name_as_username_when_no_nickname(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'nickname' => null,
        ]);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('testuser', $array['display_name']);
    }

    // ==================== avatar url ====================

    #[Test]
    public function it_returns_guild_avatar_url_when_guild_avatar_is_set(): void
    {
        $user = User::factory()->create([
            'id' => '123456789012345678',
            'guild_avatar' => 'abc123',
            'avatar' => 'def456',
        ]);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame(
            'https://cdn.discordapp.com/guilds/123456789/users/123456789012345678/avatars/abc123.webp',
            $array['avatar']
        );
    }

    #[Test]
    public function it_returns_user_avatar_url_when_no_guild_avatar(): void
    {
        $user = User::factory()->create([
            'id' => '123456789012345678',
            'guild_avatar' => null,
            'avatar' => 'def456',
        ]);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame(
            'https://cdn.discordapp.com/avatars/123456789012345678/def456.webp',
            $array['avatar']
        );
    }

    #[Test]
    public function it_returns_default_avatar_url_when_no_avatar(): void
    {
        $user = User::factory()->create([
            'id' => '175928847299117063',
            'guild_avatar' => null,
            'avatar' => null,
        ]);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('https://cdn.test/discord-cdn/embed/avatars/2.png', $array['avatar']);
    }

    // ==================== highest role ====================

    #[Test]
    public function it_returns_the_highest_visible_role_as_a_badge(): void
    {
        DiscordRole::factory()->officer()->create();
        $user = User::factory()->officer()->create();
        $raider = DiscordRole::factory()->raider()->create();
        $user->discordRoles()->syncWithoutDetaching([$raider->id]);

        $resource = new UserResource($user->fresh()->load('discordRoles'));
        $array = $resource->toArray(new Request);

        $this->assertInstanceOf(DiscordRoleResource::class, $array['highest_role']);
        $this->assertSame(['name' => 'Officer', 'primary_color' => 'aa3b3b'], $array['highest_role']->resolve());
    }

    #[Test]
    public function it_returns_null_highest_role_when_no_roles(): void
    {
        $user = User::factory()->noRoles()->create();

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertNull($array['highest_role']);
    }

    #[Test]
    public function it_returns_officer_as_highest_when_multiple_roles(): void
    {
        $officer = DiscordRole::find('829021769448816691') ??
            DiscordRole::factory()->officer()->create();
        $member = DiscordRole::find('829022020301094922') ??
            DiscordRole::factory()->member()->create();
        $guest = DiscordRole::find('829022292590985226') ??
            DiscordRole::factory()->guest()->create();

        $user = User::factory()->withRoles([
            $member->id,
            $officer->id,
            $guest->id,
        ])->create();

        $resource = new UserResource($user->fresh()->load('discordRoles'));
        $array = $resource->toArray(new Request);

        $this->assertSame('Officer', $array['highest_role']->resolve()['name']);
    }

    // ==================== admin ====================

    #[Test]
    public function it_returns_admin_true_for_admin_users(): void
    {
        $user = User::factory()->admin()->create();

        $array = (new UserResource($user))->toArray(new Request);

        $this->assertTrue($array['admin']);
    }

    #[Test]
    public function it_returns_admin_false_for_non_admin_users(): void
    {
        $user = User::factory()->create();

        $array = (new UserResource($user))->toArray(new Request);

        $this->assertFalse($array['admin']);
    }
}
