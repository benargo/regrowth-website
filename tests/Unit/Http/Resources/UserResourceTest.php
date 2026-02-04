<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\UserResource;
use App\Models\DiscordRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.discord.guild_id', '123456789');
    }

    #[Test]
    public function it_returns_id(): void
    {
        $user = User::factory()->create();

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame($user->id, $array['id']);
    }

    #[Test]
    public function it_returns_username(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('testuser', $array['username']);
    }

    #[Test]
    public function it_returns_discriminator(): void
    {
        $user = User::factory()->create(['discriminator' => '1234']);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('1234', $array['discriminator']);
    }

    #[Test]
    public function it_returns_nickname(): void
    {
        $user = User::factory()->create(['nickname' => 'TestNick']);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('TestNick', $array['nickname']);
    }

    #[Test]
    public function it_returns_null_nickname_when_not_set(): void
    {
        $user = User::factory()->create(['nickname' => null]);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertNull($array['nickname']);
    }

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
            'id' => '123456789012345678',
            'guild_avatar' => null,
            'avatar' => null,
        ]);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        // The default avatar index is based on (id >> 22) % 6
        $expectedIndex = ((int) '123456789012345678' >> 22) % 6;
        $this->assertSame(
            "https://cdn.discordapp.com/embed/avatars/{$expectedIndex}.png",
            $array['avatar']
        );
    }

    #[Test]
    public function it_returns_banner_url_when_banner_is_set(): void
    {
        $user = User::factory()->create([
            'id' => '123456789012345678',
            'banner' => 'banner123',
        ]);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame(
            'https://cdn.discordapp.com/banners/123456789012345678/banner123.webp',
            $array['banner']
        );
    }

    #[Test]
    public function it_returns_null_banner_when_not_set(): void
    {
        $user = User::factory()->create(['banner' => null]);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertNull($array['banner']);
    }

    #[Test]
    public function it_returns_roles(): void
    {
        $officer = DiscordRole::find('829021769448816691') ??
            DiscordRole::factory()->officer()->create();
        $member = DiscordRole::find('829022020301094922') ??
            DiscordRole::factory()->member()->create();

        $user = User::factory()->create();
        $user->discordRoles()->attach([$officer->id, $member->id]);

        $resource = new UserResource($user->fresh()->load('discordRoles'));
        $array = $resource->toArray(new Request);

        $this->assertContains($officer->id, $array['roles']);
        $this->assertContains($member->id, $array['roles']);
        $this->assertCount(2, $array['roles']);
    }

    #[Test]
    public function it_returns_empty_roles_array_when_no_roles(): void
    {
        $user = User::factory()->noRoles()->create();

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame([], $array['roles']);
    }

    #[Test]
    public function it_returns_highest_role_officer(): void
    {
        $user = User::factory()->officer()->create();

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('Officer', $array['highest_role']);
    }

    #[Test]
    public function it_returns_highest_role_raider(): void
    {
        $user = User::factory()->raider()->create();

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('Raider', $array['highest_role']);
    }

    #[Test]
    public function it_returns_highest_role_member(): void
    {
        $user = User::factory()->member()->create();

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('Member', $array['highest_role']);
    }

    #[Test]
    public function it_returns_highest_role_guest(): void
    {
        $user = User::factory()->guest()->create();

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('Guest', $array['highest_role']);
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

        $this->assertSame('Officer', $array['highest_role']);
    }

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $user = User::factory()->create();

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('username', $array);
        $this->assertArrayHasKey('discriminator', $array);
        $this->assertArrayHasKey('nickname', $array);
        $this->assertArrayHasKey('display_name', $array);
        $this->assertArrayHasKey('avatar', $array);
        $this->assertArrayHasKey('banner', $array);
        $this->assertArrayHasKey('roles', $array);
        $this->assertArrayHasKey('highest_role', $array);
    }
}
