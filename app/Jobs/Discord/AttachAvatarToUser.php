<?php

namespace App\Jobs\Discord;

use App\Enums\DiscordAvatar;
use App\Http\Integrations\Discord\DiscordCdnConnector;
use App\Http\Integrations\Discord\Exceptions\AvatarNotFoundException;
use App\Http\Integrations\Discord\Requests\Cdn\FetchGuildMemberAvatarRequest;
use App\Http\Integrations\Discord\Requests\Cdn\FetchUserAvatarRequest;
use App\Models\User;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Tries(3)]
#[Backoff([60, 300])]
class AttachAvatarToUser implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(
        public readonly string $userId,
        public readonly DiscordAvatar $avatar,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("user-avatar:{$this->userId}"))->releaseAfter(60)->expireAfter(120),
        ];
    }

    /**
     * Download the user's current avatar from the Discord CDN and store it in the avatar's media collection,
     * unless the stored avatar already has the current hash and its file exists.
     */
    public function handle(
        DiscordCdnConnector $cdn,
        FilesystemManager $storage,
        #[Config('services.discord.server_id')] string $serverId,
    ): void {
        $user = User::find($this->userId);
        $hash = $user?->{$this->avatar->hashAttribute()};

        if (! $user || $hash === null) {
            return;
        }

        if ($this->isStored($user, $hash, $storage)) {
            return;
        }

        $request = match ($this->avatar) {
            DiscordAvatar::Guild => new FetchGuildMemberAvatarRequest($serverId, $user->id, $hash),
            DiscordAvatar::User => new FetchUserAvatarRequest($user->id, $hash),
        };

        try {
            $body = $cdn->send($request)->body();
        } catch (AvatarNotFoundException) {
            return;
        }

        $user->addMediaFromString($body)
            ->usingFileName("{$hash}.webp")
            ->withCustomProperties(['hash' => $hash])
            ->toMediaCollection($this->avatar->value);
    }

    /**
     * Whether the avatar collection already holds a file with the given hash on its disk.
     */
    private function isStored(User $user, string $hash, FilesystemManager $storage): bool
    {
        $media = $user->getFirstMedia($this->avatar->value);

        return $media !== null
            && $media->getCustomProperty('hash') === $hash
            && $storage->disk($media->disk)->exists($media->getPathRelativeToRoot());
    }

    public function failed(Throwable $exception): void
    {
        Log::error('AttachAvatarToUser: job failed.', [
            'user_id' => $this->userId,
            'avatar' => $this->avatar->value,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['discord', "user:{$this->userId}"];
    }
}
