<?php

namespace Tests\Unit\Models;

use App\Models\DiscordRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new User;

        $this->assertCasts($model, [
            'id' => 'string',
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
        ]);

        $this->assertTableHas(['id' => '123456789012345678', 'username' => 'testuser']);
        $this->assertModelExists($user);
    }

    #[Test]
    public function discord_roles_returns_belongs_to_many_relationship(): void
    {
        $model = new User;

        $this->assertInstanceOf(BelongsToMany::class, $model->discordRoles());
    }

    #[Test]
    public function discord_roles_returns_associated_roles(): void
    {
        $officer = DiscordRole::find('829021769448816691') ??
            DiscordRole::factory()->officer()->create();
        $member = DiscordRole::find('829022020301094922') ??
            DiscordRole::factory()->member()->create();

        $user = User::factory()->create();
        $user->discordRoles()->attach([$officer->id, $member->id]);

        $this->assertCount(2, $user->discordRoles);
        $this->assertTrue($user->discordRoles->contains($officer));
        $this->assertTrue($user->discordRoles->contains($member));
    }

    #[Test]
    public function is_officer_returns_true_for_officer_role(): void
    {
        $user = User::factory()->officer()->create();

        $this->assertTrue($user->isOfficer());
    }

    #[Test]
    public function is_officer_returns_false_for_non_officer(): void
    {
        $user = User::factory()->member()->create();

        $this->assertFalse($user->isOfficer());
    }

    #[Test]
    public function is_raider_returns_true_for_raider_role(): void
    {
        $user = User::factory()->raider()->create();

        $this->assertTrue($user->isRaider());
    }

    #[Test]
    public function is_member_returns_true_for_member_role(): void
    {
        $user = User::factory()->member()->create();

        $this->assertTrue($user->isMember());
    }

    #[Test]
    public function is_loot_councillor_returns_true_for_loot_councillor_role(): void
    {
        $councillor = DiscordRole::find('1467994755953852590') ??
            DiscordRole::factory()->lootCouncillor()->create();
        $user = User::factory()->create();
        $user->discordRoles()->attach($councillor->id);

        $this->assertTrue($user->isLootCouncillor());
    }

    #[Test]
    public function is_loot_councillor_returns_false_for_non_loot_councillor(): void
    {
        $user = User::factory()->member()->create();

        $this->assertFalse($user->isLootCouncillor());
    }

    #[Test]
    public function is_guest_returns_true_for_guest_role(): void
    {
        $user = User::factory()->guest()->create();

        $this->assertTrue($user->isGuest());
    }

    #[Test]
    public function highest_role_returns_officer_when_has_multiple_roles(): void
    {
        $officer = DiscordRole::find('829021769448816691') ??
            DiscordRole::factory()->officer()->create();
        $raider = DiscordRole::find('1265247017215594496') ??
            DiscordRole::factory()->raider()->create();
        $member = DiscordRole::find('829022020301094922') ??
            DiscordRole::factory()->member()->create();

        $user = User::factory()->create();
        $user->discordRoles()->attach([$officer->id, $raider->id, $member->id]);

        $this->assertSame('Officer', $user->highestRole());
    }

    #[Test]
    public function highest_role_returns_raider_when_no_officer_role(): void
    {
        $raider = DiscordRole::find('1265247017215594496') ??
            DiscordRole::factory()->raider()->create();
        $member = DiscordRole::find('829022020301094922') ??
            DiscordRole::factory()->member()->create();

        $user = User::factory()->create();
        $user->discordRoles()->attach([$raider->id, $member->id]);

        $this->assertSame('Raider', $user->highestRole());
    }

    #[Test]
    public function highest_role_returns_null_when_no_recognized_roles(): void
    {
        $user = User::factory()->create();

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
        ]);

        $this->assertNull($user->banner_url);
    }
}
