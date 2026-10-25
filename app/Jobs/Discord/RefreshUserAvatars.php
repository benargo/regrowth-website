<?php

namespace App\Jobs\Discord;

use App\Contracts\HasDiscordAvatars;
use App\Enums\DiscordAvatar;
use App\Http\Integrations\Discord\DiscordCdnConnector;
use App\Http\Integrations\Discord\Requests\Cdn\FetchDefaultAvatarRequest;
use App\Models\User;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshUserAvatars implements HasDiscordAvatars, ShouldQueue
{
    use Queueable;

    /**
     * Store any missing default avatars on the media disk, re-dispatch the avatar download for every user hash, and clear collections whose hash is null.
     */
    public function handle(DiscordCdnConnector $cdn, FilesystemManager $filesystem, #[Config('media-library.disk_name')] string $diskName): void
    {
        $disk = $filesystem->disk($diskName);

        foreach (range(0, self::DEFAULT_AVATAR_COUNT - 1) as $index) {
            $path = User::defaultAvatarStoragePath($index);

            if ($disk->exists($path)) {
                continue;
            }

            try {
                $disk->put($path, $cdn->send(new FetchDefaultAvatarRequest($index))->body());
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        foreach (User::with('media')->lazyById() as $user) {
            foreach (DiscordAvatar::cases() as $avatar) {
                if ($user->{$avatar->hashAttribute()} !== null) {
                    AttachAvatarToUser::dispatch($user->id, $avatar);
                } elseif ($user->getFirstMedia($avatar->value) !== null) {
                    $user->clearMediaCollection($avatar->value);
                }
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('RefreshUserAvatars: job failed.', [
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['discord', 'discord:avatars'];
    }
}
