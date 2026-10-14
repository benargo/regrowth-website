<?php

namespace App\Jobs\Discord;

use App\Actions\Discord\SyncUserDiscordRoles;
use App\Events\Broadcasts\DiscordMemberResolved;
use App\Http\Integrations\Discord\Data\Users\UserData;
use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Exceptions\UserNotInGuildException;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildMemberRequest;
use App\Jobs\Middleware\ReleaseWhenDiscordRateLimited;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Optional;
use Throwable;

/**
 * Makes sure a Discord member has a users row, creating it from their guild member
 * data with their recognised roles, then broadcasts DiscordMemberResolved to the
 * requesting officer: `ready`, `not_in_guild`, or `failed` once retries run out.
 */
#[Queue('high')]
#[MaxExceptions(1)]
class ResolveGuildMember implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $memberId, public string $requestedBy) {}

    /**
     * Rate-limit releases keep the job alive until this deadline.
     */
    public function retryUntil(): CarbonInterface
    {
        return now()->addMinutes(10);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new ReleaseWhenDiscordRateLimited];
    }

    public function handle(DiscordConnector $discord, #[Config('services.discord.server_id')] string $serverId): void
    {
        if (! User::whereKey($this->memberId)->exists()) {
            try {
                $member = $discord->send(new GetGuildMemberRequest($serverId, $this->memberId))->dto();
            } catch (UserNotInGuildException) {
                DiscordMemberResolved::dispatch($this->requestedBy, $this->memberId, 'not_in_guild');

                return;
            }

            $discordUser = $member->user instanceof UserData ? $member->user : null;

            DB::transaction(function () use ($member, $discordUser): void {
                $user = User::createOrFirst(['id' => $this->memberId], array_filter([
                    'username' => $discordUser?->username,
                    'discriminator' => $discordUser?->discriminator ?? '0',
                    'nickname' => $member->nick,
                    'avatar' => $discordUser?->avatar,
                    'guild_avatar' => $member->avatar,
                    'banner' => $member->banner,
                ], fn (mixed $value): bool => ! $value instanceof Optional));

                SyncUserDiscordRoles::run($user, $member->roles);
            });
        }

        DiscordMemberResolved::dispatch($this->requestedBy, $this->memberId, 'ready');
    }

    public function failed(Throwable $exception): void
    {
        DiscordMemberResolved::dispatch($this->requestedBy, $this->memberId, 'failed');
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['discord', 'discord:users', "discord:user:{$this->memberId}"];
    }
}
