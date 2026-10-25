<?php

namespace Tests\Feature\Observers;

use App\Enums\DiscordAvatar;
use App\Jobs\Discord\AttachAvatarToUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('account')]
class UserObserverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_dispatches_for_each_hash_on_create(): void
    {
        $user = User::factory()->create(['avatar' => 'user-hash', 'guild_avatar' => 'guild-hash']);

        Queue::assertPushed(AttachAvatarToUser::class, 2);
        Queue::assertPushed(AttachAvatarToUser::class, fn (AttachAvatarToUser $job) => $job->userId === $user->id && $job->avatar === DiscordAvatar::User);
        Queue::assertPushed(AttachAvatarToUser::class, fn (AttachAvatarToUser $job) => $job->userId === $user->id && $job->avatar === DiscordAvatar::Guild);
    }

    #[Test]
    public function it_does_not_dispatch_on_create_without_hashes(): void
    {
        User::factory()->create(['avatar' => null, 'guild_avatar' => null]);

        Queue::assertNotPushed(AttachAvatarToUser::class);
    }

    #[Test]
    public function it_does_not_dispatch_again_when_a_new_user_is_saved_without_hash_changes(): void
    {
        $user = User::factory()->create(['avatar' => 'user-hash', 'guild_avatar' => 'guild-hash']);

        $user->setRememberToken('new-token');
        $user->save();

        Queue::assertPushed(AttachAvatarToUser::class, 2);
    }

    #[Test]
    public function it_dispatches_only_for_the_hash_that_changed(): void
    {
        $user = $this->userWithoutEvents(['avatar' => 'user-hash', 'guild_avatar' => 'guild-hash']);

        $user->update(['guild_avatar' => 'new-guild-hash']);

        Queue::assertPushed(AttachAvatarToUser::class, 1);
        Queue::assertPushed(AttachAvatarToUser::class, fn (AttachAvatarToUser $job) => $job->userId === $user->id && $job->avatar === DiscordAvatar::Guild);
    }

    #[Test]
    public function it_does_not_dispatch_when_no_hash_changed(): void
    {
        $user = $this->userWithoutEvents(['avatar' => 'user-hash', 'guild_avatar' => 'guild-hash']);

        $user->update(['nickname' => 'Renamed']);

        Queue::assertNotPushed(AttachAvatarToUser::class);
    }

    #[Test]
    public function it_clears_the_collection_when_a_hash_becomes_null(): void
    {
        Storage::fake('s3');
        $user = $this->userWithoutEvents(['avatar' => 'user-hash', 'guild_avatar' => 'guild-hash']);
        $user->addMedia(UploadedFile::fake()->image('guild.png'))->toMediaCollection(DiscordAvatar::Guild->value);
        $this->assertCount(1, $user->fresh()->getMedia(DiscordAvatar::Guild->value));

        $user->update(['guild_avatar' => null]);

        $this->assertCount(0, $user->fresh()->getMedia(DiscordAvatar::Guild->value));
        Queue::assertNotPushed(AttachAvatarToUser::class);
    }

    /**
     * Create a user without firing model events.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function userWithoutEvents(array $attributes): User
    {
        return User::withoutEvents(fn () => User::factory()->create($attributes))->fresh();
    }
}
