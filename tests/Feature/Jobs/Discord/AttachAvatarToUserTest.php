<?php

namespace Tests\Feature\Jobs\Discord;

use App\Enums\DiscordAvatar;
use App\Http\Integrations\Discord\Requests\Cdn\FetchGuildMemberAvatarRequest;
use App\Http\Integrations\Discord\Requests\Cdn\FetchUserAvatarRequest;
use App\Jobs\Discord\AttachAvatarToUser;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

#[Group('discord-integration')]
#[Group('account')]
class AttachAvatarToUserTest extends TestCase
{
    use RefreshDatabase;

    private const USER_ID = '100000000000000000';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
    }

    #[Test]
    public function it_stores_the_guild_avatar_with_its_hash(): void
    {
        $user = User::factory()->create(['id' => self::USER_ID, 'guild_avatar' => 'guildhash', 'avatar' => 'userhash']);
        Saloon::fake([FetchGuildMemberAvatarRequest::class => MockResponse::make($this->body())]);

        $this->runJob(DiscordAvatar::Guild);

        $this->assertSame('guildhash', $user->getFirstMedia('discord_guild_avatar')->getCustomProperty('hash'));
        $this->assertNull($user->getFirstMedia('discord_avatar'));
        Saloon::assertSent(FetchGuildMemberAvatarRequest::class);
        Saloon::assertNotSent(FetchUserAvatarRequest::class);
    }

    #[Test]
    public function it_stores_the_user_avatar_with_its_hash(): void
    {
        $user = User::factory()->create(['id' => self::USER_ID, 'guild_avatar' => 'guildhash', 'avatar' => 'userhash']);
        Saloon::fake([FetchUserAvatarRequest::class => MockResponse::make($this->body())]);

        $this->runJob(DiscordAvatar::User);

        $this->assertSame('userhash', $user->getFirstMedia('discord_avatar')->getCustomProperty('hash'));
        $this->assertNull($user->getFirstMedia('discord_guild_avatar'));
        Saloon::assertSent(FetchUserAvatarRequest::class);
        Saloon::assertNotSent(FetchGuildMemberAvatarRequest::class);
    }

    #[Test]
    public function it_replaces_the_previous_avatar(): void
    {
        $user = User::factory()->create(['id' => self::USER_ID, 'avatar' => 'first']);
        Saloon::fake([FetchUserAvatarRequest::class => MockResponse::make($this->body())]);
        $this->runJob(DiscordAvatar::User);

        $user->update(['avatar' => 'second']);
        $this->runJob(DiscordAvatar::User);

        $media = $user->fresh()->getMedia('discord_avatar');
        $this->assertCount(1, $media);
        $this->assertSame('second', $media->first()->getCustomProperty('hash'));
    }

    #[Test]
    public function it_skips_the_download_when_the_stored_avatar_has_the_current_hash(): void
    {
        $user = User::factory()->create(['id' => self::USER_ID, 'avatar' => 'samehash']);
        $mediaId = $user->addMediaFromString($this->body())
            ->usingFileName('samehash.webp')
            ->withCustomProperties(['hash' => 'samehash'])
            ->toMediaCollection('discord_avatar')
            ->id;
        Saloon::fake([FetchUserAvatarRequest::class => MockResponse::make($this->body())]);

        $this->runJob(DiscordAvatar::User);

        Saloon::assertNothingSent();
        $this->assertSame($mediaId, $user->fresh()->getFirstMedia('discord_avatar')->id);
    }

    #[Test]
    public function it_downloads_again_when_the_stored_avatar_file_is_missing(): void
    {
        $user = User::factory()->create(['id' => self::USER_ID, 'avatar' => 'samehash']);
        Saloon::fake([FetchUserAvatarRequest::class => MockResponse::make($this->body())]);
        $this->runJob(DiscordAvatar::User);
        Storage::disk('s3')->delete($user->getFirstMedia('discord_avatar')->getPathRelativeToRoot());

        $this->runJob(DiscordAvatar::User);

        Saloon::assertSentCount(2);
        Storage::disk('s3')->assertExists($user->fresh()->getFirstMedia('discord_avatar')->getPathRelativeToRoot());
    }

    #[Test]
    public function it_downloads_again_when_the_hash_has_changed(): void
    {
        $user = User::factory()->create(['id' => self::USER_ID, 'avatar' => 'oldhash']);
        Saloon::fake([FetchUserAvatarRequest::class => MockResponse::make($this->body())]);
        $this->runJob(DiscordAvatar::User);
        $user->update(['avatar' => 'newhash']);

        $this->runJob(DiscordAvatar::User);

        Saloon::assertSentCount(2);
        $this->assertSame('newhash', $user->fresh()->getFirstMedia('discord_avatar')->getCustomProperty('hash'));
    }

    #[Test]
    public function it_attaches_nothing_when_the_cdn_returns_404(): void
    {
        $user = User::factory()->create(['id' => self::USER_ID, 'avatar' => 'gone']);
        Saloon::fake([FetchUserAvatarRequest::class => MockResponse::make('', 404)]);

        $this->runJob(DiscordAvatar::User);

        $this->assertNull($user->getFirstMedia('discord_avatar'));
    }

    #[Test]
    public function it_does_nothing_for_a_missing_user_or_null_hash(): void
    {
        Saloon::fake([]);
        User::factory()->create(['id' => self::USER_ID, 'avatar' => null]);

        $this->runJob(DiscordAvatar::User);
        $this->runJob(DiscordAvatar::User, '999999999999999999');

        Saloon::assertNothingSent();
    }

    #[Test]
    #[Group('contract')]
    public function it_declares_its_queue_contract(): void
    {
        $job = new AttachAvatarToUser(self::USER_ID, DiscordAvatar::User);
        $class = new ReflectionClass($job);

        $this->assertInstanceOf(ShouldQueueAfterCommit::class, $job);
        $this->assertSame(3, $class->getAttributes(Tries::class)[0]->newInstance()->tries);
        $this->assertSame([60, 300], $class->getAttributes(Backoff::class)[0]->newInstance()->backoff);
        $this->assertSame(['discord', 'user:'.self::USER_ID], $job->tags());

        $middleware = $job->middleware()[0];
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware);
        $this->assertSame('user-avatar:'.self::USER_ID.':'.DiscordAvatar::User->value, $middleware->key);
        $this->assertSame(120, $middleware->expiresAfter);
    }

    /**
     * A fake 256x256 webp image body.
     */
    private function body(): string
    {
        return UploadedFile::fake()->image('a.webp', 256, 256)->getContent();
    }

    /**
     * Run the job's handle method through the container for the given avatar and user.
     */
    private function runJob(DiscordAvatar $avatar, string $userId = self::USER_ID): void
    {
        app()->call([new AttachAvatarToUser($userId, $avatar), 'handle']);
    }
}
