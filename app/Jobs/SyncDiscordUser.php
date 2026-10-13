<?php

namespace App\Jobs;

use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Exceptions\UserNotInGuildException;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildMemberRequest;
use App\Jobs\Middleware\ReleaseWhenDiscordRateLimited;
use App\Models\DiscordRole;
use App\Models\User;
use Illuminate\Bus\Batchable;
use Illuminate\Container\Attributes\Config;
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
            new ReleaseWhenDiscordRateLimited,
        ];
    }

    public function handle(DiscordConnector $discord, #[Config('services.discord.server_id')] string $serverId): void
    {
        $user = User::find($this->id);

        if (! $user) {
            Log::warning('SyncDiscordUser: user not found.', ['user_id' => $this->id]);

            return;
        }

        try {
            $member = $discord->send(new GetGuildMemberRequest($serverId, $user->id))->dto();

            $user->update([
                'nickname' => $member->nick,
                'guild_avatar' => $member->avatar,
                'banner' => $member->banner,
            ]);

            $recognizedRoleIds = DiscordRole::whereIn('id', $member->roles)->pluck('id')->toArray();
            $user->discordRoles()->sync($recognizedRoleIds);
        } catch (UserNotInGuildException) {
            $user->delete();
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
