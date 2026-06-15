<?php

namespace App\Jobs;

use App\Models\DiscordRole;
use App\Models\User;
use App\Services\Discord\Discord;
use App\Services\Discord\Exceptions\RateLimitedException;
use App\Services\Discord\Exceptions\UserNotInGuildException;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

#[Tries(3)]
#[Backoff(60)]
class SyncDiscordUser implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(public string $id) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new SkipIfBatchCancelled,
            (new WithoutOverlapping)->dontRelease(),
        ];
    }

    public function handle(Discord $discord): void
    {
        $user = User::find($this->id);

        if (! $user) {
            Log::warning('SyncDiscordUser: user not found.', ['user_id' => $this->id]);

            return;
        }

        try {
            $member = $discord->getGuildMember($user->id);

            $user->update([
                'nickname' => $member->nick,
                'guild_avatar' => $member->avatar,
                'banner' => $member->banner,
            ]);

            $recognizedRoleIds = DiscordRole::whereIn('id', $member->roles)->pluck('id')->toArray();
            $user->discordRoles()->sync($recognizedRoleIds);
        } catch (UserNotInGuildException) {
            $user->delete();
        } catch (RateLimitedException $e) {
            Log::warning('SyncDiscordUser: Discord rate limited.', [
                'user_id' => $this->id,
                'endpoint' => $e->endpoint,
                'retry_after' => $e->retryAfter,
                'scope' => $e->scope,
            ]);
            $this->release($e->retryAfter);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncDiscordUser: job failed.', [
            'user_id' => $this->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['discord', 'discord:users', "discord:user:{$this->id}"];
    }
}
