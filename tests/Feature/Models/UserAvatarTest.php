<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('account')]
class UserAvatarTest extends TestCase
{
    use RefreshDatabase;

    private const GUILD_ID = '829020506907869214';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        config(['services.discord.server_id' => self::GUILD_ID]);
    }

    #[Test]
    #[Group('laravel-media-library')]
    public function it_prefers_stored_guild_avatar_media(): void
    {
        $user = User::factory()->create(['avatar' => 'u', 'guild_avatar' => 'g']);
        $this->attachAvatar($user, 'discord_guild_avatar');
        $user = $user->fresh();

        $this->assertSame($user->getFirstMediaUrl('discord_guild_avatar'), $user->avatarUrl);
        $this->assertSame($user->getFirstMediaUrl('discord_guild_avatar', 'thumb'), $user->avatarThumbUrl);
    }

    #[Test]
    public function it_falls_back_to_the_discord_guild_cdn_url_before_media_exists(): void
    {
        $user = User::factory()->create(['id' => '123', 'avatar' => 'u', 'guild_avatar' => 'g']);

        $expected = 'https://cdn.discordapp.com/guilds/'.self::GUILD_ID.'/users/123/avatars/g.webp';
        $this->assertSame($expected, $user->avatarUrl);
        $this->assertSame($expected, $user->avatarThumbUrl);
    }

    #[Test]
    #[Group('laravel-media-library')]
    public function it_uses_stored_user_avatar_media_when_there_is_no_guild_avatar(): void
    {
        $user = User::factory()->create(['avatar' => 'u', 'guild_avatar' => null]);
        $this->attachAvatar($user, 'discord_avatar');
        $user = $user->fresh();

        $this->assertSame($user->getFirstMediaUrl('discord_avatar'), $user->avatarUrl);
        $this->assertSame($user->getFirstMediaUrl('discord_avatar', 'thumb'), $user->avatarThumbUrl);
    }

    #[Test]
    public function it_falls_back_to_the_discord_user_cdn_url(): void
    {
        $user = User::factory()->create(['id' => '123', 'avatar' => 'u', 'guild_avatar' => null]);

        $this->assertSame('https://cdn.discordapp.com/avatars/123/u.webp', $user->avatarUrl);
    }

    #[Test]
    public function it_serves_the_stored_default_avatar_when_the_user_has_no_hashes(): void
    {
        $user = User::factory()->create(['id' => '100000000000000003', 'avatar' => null, 'guild_avatar' => null]);

        $expected = Storage::disk('s3')->url('discord-cdn/embed/avatars/'.((100000000000000003 >> 22) % 6).'.png');
        $this->assertSame($expected, $user->avatarUrl);
        $this->assertSame($expected, $user->avatarThumbUrl);
    }

    #[Test]
    #[Group('laravel-media-library')]
    public function it_generates_a_160px_webp_thumb(): void
    {
        $user = User::factory()->create(['guild_avatar' => 'g']);
        $this->attachAvatar($user, 'discord_guild_avatar');
        $media = $user->fresh()->getFirstMedia('discord_guild_avatar');

        $this->assertTrue($media->hasGeneratedConversion('thumb'));
        $path = $media->getPathRelativeToRoot('thumb');
        $this->assertTrue(Storage::disk('s3')->exists($path));
        $this->assertStringEndsWith('.webp', $path);
        $this->assertSame(160, getimagesizefromstring(Storage::disk('s3')->get($path))[0]);
    }

    /**
     * Store a fake 256x256 PNG in the given avatar collection of the user.
     */
    private function attachAvatar(User $user, string $collection): void
    {
        $user->addMediaFromString(UploadedFile::fake()->image('a.png', 256, 256)->getContent())
            ->usingFileName('a.png')
            ->toMediaCollection($collection);
    }
}
