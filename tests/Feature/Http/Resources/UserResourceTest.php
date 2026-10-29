<?php

namespace Tests\Feature\Http\Resources;

use App\Http\Resources\PresenceUserResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('account')]
#[Group('laravel-media-library')]
class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
    }

    #[Test]
    public function user_resource_returns_the_thumb_url_for_stored_media(): void
    {
        $user = User::factory()->create(['guild_avatar' => 'g']);
        $user->addMediaFromString(UploadedFile::fake()->image('a.png', 256, 256)->getContent())
            ->usingFileName('a.png')->toMediaCollection('discord_guild_avatar');
        $user = $user->fresh();

        $this->assertSame($user->avatarThumbUrl, (new UserResource($user))->resolve(new Request)['avatar']);
        $this->assertSame($user->avatarThumbUrl, (new PresenceUserResource($user))->resolve(new Request)['avatar_url']);
    }

    #[Test]
    public function resources_return_the_thumb_url_without_stored_media(): void
    {
        $user = User::factory()->create(['avatar' => null, 'guild_avatar' => null]);

        $this->assertSame($user->avatarThumbUrl, (new UserResource($user))->resolve(new Request)['avatar']);
        $this->assertSame($user->avatarThumbUrl, (new PresenceUserResource($user))->resolve(new Request)['avatar_url']);
    }
}
