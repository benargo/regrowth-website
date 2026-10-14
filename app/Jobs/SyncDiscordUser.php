<?php

namespace App\Jobs;

use App\Actions\Discord\SyncUserDiscordRoles;
use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Exceptions\UserNotInGuildException;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildMemberRequest;
use App\Jobs\Middleware\ReleaseWhenDiscordRateLimited;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelData\Optional;
use Throwable;

#[MaxExceptions(3)]
#[Backoff(60)]
class SyncDiscordUser implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(public string $id) {}

    /**
     * Rate-limit releases keep the job alive until this deadline.
     */
    public function retryUntil(): CarbonInterface
    {
        return now()->addHour();
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new SkipIfBatchCancelled,
            (new WithoutOverlapping($this->id))->dontRelease()->expireAfter(120),
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

            $user->update(Arr::reject([
                'nickname' => $member->nick,
                'guild_avatar' => $member->avatar,
                'banner' => $member->banner,
            ], fn (mixed $value): bool => $value instanceof Optional));

            SyncUserDiscordRoles::run($user, $member->roles);
        } catch (UserNotInGuildException) {
            $user->delete();
        }
    }

    public function failed(Throwable $exception): void
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
