<?php

namespace Tests\Unit\Models;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class UserTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return User::class;
    }

    #[Test]
    public function it_uses_discord_id_as_primary_key(): void
    {
        $model = new User;

        $this->assertSame('id', $model->getKeyName());
        $this->assertSame('string', $model->getKeyType());
        $this->assertFalse($model->getIncrementing());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new User;

        $this->assertFillable($model, [
            'id',
            'username',
            'discriminator',
            'nickname',
            'avatar',
            'guild_avatar',
            'banner',
            'roles',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new User;

        $this->assertCasts($model, [
            'id' => 'string',
            'roles' => 'array',
        ]);
    }

    #[Test]
    public function it_does_not_have_password_in_fillable(): void
    {
        $model = new User;

        $this->assertNotContains('password', $model->getFillable());
    }

    #[Test]
    public function it_persists_with_discord_id_as_primary_key(): void
    {
        $user = $this->create([
            'id' => '123456789012345678',
            'username' => 'testuser',
            'discriminator' => '0',
            'roles' => ['829021769448816691'],
        ]);

        $this->assertTableHas(['id' => '123456789012345678', 'username' => 'testuser']);
        $this->assertModelExists($user);
    }

    #[Test]
    public function it_casts_roles_to_array(): void
    {
        $user = $this->create([
            'id' => '123456789012345678',
            'username' => 'testuser',
            'discriminator' => '0',
            'roles' => ['829021769448816691', '1265247017215594496'],
        ]);

        $this->assertIsArray($user->roles);
        $this->assertContains('829021769448816691', $user->roles);
    }

    #[Test]
    public function is_officer_returns_true_for_officer_role(): void
    {
        $user = $this->create([
            'id' => '123456789012345678',
            'username' => 'officer_user',
            'discriminator' => '0',
            'roles' => ['829021769448816691'], // Officer role
        ]);

        $this->assertTrue($user->isOfficer());
    }

    #[Test]
    public function is_officer_returns_false_for_non_officer(): void
    {
        $user = $this->create([
            'id' => '123456789012345679',
            'username' => 'member_user',
            'discriminator' => '0',
            'roles' => ['829022020301094922'], // Member role
        ]);

        $this->assertFalse($user->isOfficer());
    }

    #[Test]
    public function is_raider_returns_true_for_raider_role(): void
    {
        $user = $this->create([
            'id' => '123456789012345680',
            'username' => 'raider_user',
            'discriminator' => '0',
            'roles' => ['1265247017215594496'], // Raider role
        ]);

        $this->assertTrue($user->isRaider());
    }

    #[Test]
    public function is_member_returns_true_for_member_role(): void
    {
        $user = $this->create([
            'id' => '123456789012345681',
            'username' => 'member_user',
            'discriminator' => '0',
            'roles' => ['829022020301094922'], // Member role
        ]);

        $this->assertTrue($user->isMember());
    }

    #[Test]
    public function is_guest_returns_true_for_guest_role(): void
    {
        $user = $this->create([
            'id' => '123456789012345682',
            'username' => 'guest_user',
            'discriminator' => '0',
            'roles' => ['829022292590985226'], // Guest role
        ]);

        $this->assertTrue($user->isGuest());
    }

    #[Test]
    public function highest_role_returns_officer_when_has_multiple_roles(): void
    {
        $user = $this->create([
            'id' => '123456789012345683',
            'username' => 'multi_role_user',
            'discriminator' => '0',
            'roles' => ['829021769448816691', '1265247017215594496', '829022020301094922'],
        ]);

        $this->assertSame('Officer', $user->highestRole());
    }

    #[Test]
    public function highest_role_returns_raider_when_no_officer_role(): void
    {
        $user = $this->create([
            'id' => '123456789012345684',
            'username' => 'raider_member_user',
            'discriminator' => '0',
            'roles' => ['1265247017215594496', '829022020301094922'],
        ]);

        $this->assertSame('Raider', $user->highestRole());
    }

    #[Test]
    public function highest_role_returns_null_when_no_recognized_roles(): void
    {
        $user = $this->create([
            'id' => '123456789012345685',
            'username' => 'unknown_role_user',
            'discriminator' => '0',
            'roles' => ['999999999999999999'],
        ]);

        $this->assertNull($user->highestRole());
    }

    #[Test]
    public function display_name_returns_nickname_when_set(): void
    {
        $user = $this->create([
            'id' => '123456789012345686',
            'username' => 'testuser',
            'nickname' => 'MyNickname',
            'discriminator' => '0',
            'roles' => [],
        ]);

        $this->assertSame('MyNickname', $user->display_name);
    }

    #[Test]
    public function display_name_returns_username_when_nickname_is_null(): void
    {
        $user = $this->create([
            'id' => '123456789012345687',
            'username' => 'testuser',
            'nickname' => null,
            'discriminator' => '0',
            'roles' => [],
        ]);

        $this->assertSame('testuser', $user->display_name);
    }

    #[Test]
    public function avatar_url_returns_cdn_url_when_guild_avatar_set(): void
    {
        $user = $this->create([
            'id' => '123456789012345688',
            'username' => 'testuser',
            'discriminator' => '0',
            'avatar' => 'abc123def456',
            'guild_avatar' => 'def456abc123',
            'roles' => [],
        ]);

        $this->assertSame(
            'https://cdn.discordapp.com/guilds/829020506907869214/users/123456789012345688/avatars/def456abc123.webp',
            $user->avatar_url
        );
    }

    #[Test]
    public function avatar_url_returns_cdn_url_when_avatar_set(): void
    {
        $user = $this->create([
            'id' => '123456789012345688',
            'username' => 'testuser',
            'discriminator' => '0',
            'avatar' => 'abc123def456',
            'guild_avatar' => null,
            'roles' => [],
        ]);

        $this->assertSame(
            'https://cdn.discordapp.com/avatars/123456789012345688/abc123def456.webp',
            $user->avatar_url
        );
    }

    #[Test]
    public function avatar_url_returns_default_avatar_when_avatar_is_null(): void
    {
        $user = $this->create([
            'id' => '123456789012345689',
            'username' => 'testuser',
            'discriminator' => '0',
            'avatar' => null,
            'guild_avatar' => null,
            'roles' => [],
        ]);

        $this->assertStringStartsWith('https://cdn.discordapp.com/embed/avatars/', $user->avatar_url);
    }

    #[Test]
    public function banner_url_returns_cdn_url_when_banner_set(): void
    {
        $user = $this->create([
            'id' => '123456789012345690',
            'username' => 'testuser',
            'discriminator' => '0',
            'banner' => 'banner123',
            'roles' => [],
        ]);

        $this->assertSame(
            'https://cdn.discordapp.com/banners/123456789012345690/banner123.webp',
            $user->banner_url
        );
    }

    #[Test]
    public function banner_url_returns_null_when_banner_is_null(): void
    {
        $user = $this->create([
            'id' => '123456789012345691',
            'username' => 'testuser',
            'discriminator' => '0',
            'banner' => null,
            'roles' => [],
        ]);

        $this->assertNull($user->banner_url);
    }
}
