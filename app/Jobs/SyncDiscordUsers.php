<?php

namespace App\Jobs;

use App\Models\DiscordRole;
use App\Models\User;
use App\Services\Discord\Discord;
use App\Services\Discord\Exceptions\UserNotInGuildException;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

class SyncDiscordUsers implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * Define the middleware for the job.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new SkipIfBatchCancelled,
            new WithoutOverlapping,
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(Discord $discord): void
    {
        $users = User::all();

        $synced = 0;
        $deleted = 0;
        $errored = 0;

        foreach ($users as $user) {
            try {
                $member = $discord->getGuildMember($user->id);

                $user->update([
                    'nickname' => $member->nick,
                    'guild_avatar' => $member->avatar,
                    'banner' => $member->banner,
                ]);

                $incomingRoleIds = $member->roles;
                $recognizedRoleIds = DiscordRole::whereIn('id', $incomingRoleIds)->pluck('id')->toArray();
                $user->discordRoles()->sync($recognizedRoleIds);

                $synced++;
            } catch (UserNotInGuildException $e) {
                $user->delete();
                $deleted++;
            } catch (\Throwable $e) {
                Log::warning('Failed to sync Discord user.', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $errored++;
            }
        }

        Log::info('Synchronising Discord users job completed.', [
            'synced' => $synced,
            'deleted' => $deleted,
            'errored' => $errored,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Synchronising Discord users job failed.', [
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['discord', 'discord:users'];
    }
}
