<?php

namespace Tests\Feature\Jobs\Discord;

use App\Enums\DiscordAvatar;
use App\Http\Integrations\Discord\Requests\Cdn\FetchDefaultAvatarRequest;
use App\Jobs\Discord\AttachAvatarToUser;
use App\Jobs\Discord\RefreshUserAvatars;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

#[Group('discord-integration')]
#[Group('account')]
class RefreshUserAvatarsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
    }

    #[Test]
    public function it_writes_missing_default_avatars(): void
    {
        Storage::disk('s3')->put('discord-cdn/embed/avatars/0.png', 'existing');
        Saloon::fake([FetchDefaultAvatarRequest::class => MockResponse::make('png-bytes')]);

        $this->runJob();

        foreach (range(1, 5) as $index) {
            Storage::disk('s3')->assertExists("discord-cdn/embed/avatars/{$index}.png");
        }
        $this->assertSame('existing', Storage::disk('s3')->get('discord-cdn/embed/avatars/0.png'));
        Saloon::assertSentCount(5);
    }

    #[Test]
    public function it_continues_when_one_default_avatar_404s(): void
    {
        Saloon::fake([
            MockResponse::make('png-bytes'),
            MockResponse::make('png-bytes'),
            MockResponse::make('', 404),
            MockResponse::make('png-bytes'),
            MockResponse::make('png-bytes'),
            MockResponse::make('png-bytes'),
        ]);

        $this->runJob();

        Storage::disk('s3')->assertMissing('discord-cdn/embed/avatars/2.png');
        foreach ([0, 1, 3, 4, 5] as $index) {
            Storage::disk('s3')->assertExists("discord-cdn/embed/avatars/{$index}.png");
        }
    }

    #[Test]
    public function it_dispatches_attach_jobs_for_every_user_and_hash(): void
    {
        $this->fakeAllDefaults();
        [$first, $second] = User::withoutEvents(fn () => [
            User::factory()->create(['avatar' => 'a1', 'guild_avatar' => 'g1']),
            User::factory()->create(['avatar' => 'a2', 'guild_avatar' => null]),
        ]);
        Queue::fake();

        $this->runJob();

        Queue::assertPushed(AttachAvatarToUser::class, 3);
        Queue::assertPushed(AttachAvatarToUser::class, fn (AttachAvatarToUser $job) => $job->userId === $first->id && $job->avatar === DiscordAvatar::User);
        Queue::assertPushed(AttachAvatarToUser::class, fn (AttachAvatarToUser $job) => $job->userId === $first->id && $job->avatar === DiscordAvatar::Guild);
        Queue::assertPushed(AttachAvatarToUser::class, fn (AttachAvatarToUser $job) => $job->userId === $second->id && $job->avatar === DiscordAvatar::User);
    }

    #[Test]
    public function it_clears_collections_whose_hash_is_null(): void
    {
        $this->fakeAllDefaults();
        $user = User::withoutEvents(fn () => User::factory()->create(['avatar' => 'a1', 'guild_avatar' => null]));
        $body = UploadedFile::fake()->image('a.webp', 256, 256)->getContent();
        $user->addMediaFromString($body)->usingFileName('old.webp')->toMediaCollection(DiscordAvatar::Guild->value);
        $user->addMediaFromString($body)->usingFileName('a1.webp')->toMediaCollection(DiscordAvatar::User->value);
        Queue::fake();

        $this->runJob();

        $user->refresh();
        $this->assertNull($user->getFirstMedia(DiscordAvatar::Guild->value));
        $this->assertNotNull($user->getFirstMedia(DiscordAvatar::User->value));
    }

    /**
     * Run the job's handle method through the container.
     */
    private function runJob(): void
    {
        app()->call([new RefreshUserAvatars, 'handle']);
    }

    /**
     * Fake every default-avatar CDN request with a PNG body.
     */
    private function fakeAllDefaults(): void
    {
        Saloon::fake([FetchDefaultAvatarRequest::class => MockResponse::make('png-bytes')]);
    }
}
