<?php

namespace App\Jobs\Discord;

use App\Actions\Discord\SyncUserDiscordRoles;
use App\Http\Integrations\Discord\Data\Guilds\GuildMemberData;
use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Pagination\SnowflakePaginator;
use App\Http\Integrations\Discord\Requests\Guilds\ListGuildMembersRequest;
use App\Jobs\Middleware\ReleaseWhenDiscordRateLimited;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelData\Optional;
use Throwable;

#[MaxExceptions(3)]
#[Backoff(60)]
class SyncUsers implements ShouldQueue
{
    use Queueable;

    /**
     * Rate-limit releases keep the job alive until this deadline; three real errors still fail it.
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
            (new WithoutOverlapping)->dontRelease()->expireAfter(600),
            new ReleaseWhenDiscordRateLimited,
        ];
    }

    /**
     * Fetch every guild member, update matching local users and their roles, then delete users missing from the guild.
     *
     * Nothing is written until every page has been fetched. An empty member list changes nothing.
     * A departed user whose deletion fails is logged and skipped.
     */
    public function handle(DiscordConnector $discord, #[Config('services.discord.server_id')] string $serverId): void
    {
        $members = $this->fetchMembers($discord, $serverId);

        if ($members->isEmpty()) {
            Log::warning('Synchronising Discord users skipped: the guild returned no members.');

            return;
        }

        $updated = 0;

        User::whereIn('id', $members->keys())
            ->lazyById()
            ->each(function (User $user) use ($members, &$updated): void {
                $member = $members->get($user->id);

                $user->update(Arr::reject([
                    'nickname' => $member->nick,
                    'guild_avatar' => $member->avatar,
                    'avatar' => $member->user instanceof Optional ? $member->user : $member->user->avatar,
                ], fn (mixed $value): bool => $value instanceof Optional));

                SyncUserDiscordRoles::run($user, $member->roles);

                $updated++;
            });

        $deleted = 0;

        User::whereNotIn('id', $members->keys())
            ->lazyById()
            ->each(function (User $user) use (&$deleted): void {
                try {
                    $user->delete();
                    $deleted++;
                } catch (QueryException $exception) {
                    Log::warning('Synchronising Discord users could not delete a departed user.', [
                        'user_id' => $user->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            });

        Log::info('Synchronising Discord users job completed.', [
            'updated' => $updated,
            'deleted' => $deleted,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Synchronising Discord users job failed.', [
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['discord', 'discord:users'];
    }

    /**
     * Every guild member that has a user, keyed by user ID.
     *
     * @return Collection<string, GuildMemberData>
     */
    private function fetchMembers(DiscordConnector $discord, string $serverId): Collection
    {
        $paginator = new SnowflakePaginator(connector: $discord, request: new ListGuildMembersRequest($serverId));

        return collect($paginator->items())
            ->reject(fn (GuildMemberData $member): bool => $member->user instanceof Optional)
            ->keyBy(fn (GuildMemberData $member): string => $member->user->id);
    }
}
