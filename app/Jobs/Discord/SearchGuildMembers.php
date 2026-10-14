<?php

namespace App\Jobs\Discord;

use App\Events\Broadcasts\DiscordMembersSearched;
use App\Http\Integrations\Discord\Data\Guilds\GuildMemberData;
use App\Http\Integrations\Discord\Data\Users\UserData;
use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Requests\Guilds\SearchGuildMembersRequest;
use App\Jobs\Middleware\ReleaseWhenDiscordRateLimited;
use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Support\Facades\Cache;
use Spatie\LaravelData\Optional;
use Throwable;

/**
 * Searches the Discord server's members by username or nickname prefix, caches the
 * result for five minutes, then broadcasts DiscordMembersSearched to the requesting
 * officer, or a failed DiscordMembersSearched once retries run out.
 */
#[Queue('high')]
#[MaxExceptions(1)]
class SearchGuildMembers implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $query, public int $limit, public string $requestedBy) {}

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
        Cache::tags(['discord'])->remember(
            self::cacheKey($serverId, $this->limit, $this->query),
            now()->addMinutes(5),
            fn (): array => $discord
                ->send(new SearchGuildMembersRequest($serverId, $this->query, $this->limit))
                ->dto()
                ->map(function (GuildMemberData $member): array {
                    $user = $member->user instanceof UserData ? $member->user : null;

                    return array_filter([
                        'id' => $user?->id,
                        'nickname' => $member->nick,
                        'username' => $user?->username,
                    ], fn (mixed $value): bool => ! $value instanceof Optional);
                })
                ->all()
        );

        DiscordMembersSearched::dispatch($this->requestedBy, $this->query, $this->limit);
    }

    public function failed(Throwable $exception): void
    {
        DiscordMembersSearched::dispatch($this->requestedBy, $this->query, $this->limit, failed: true);
    }

    /**
     * Build the cache key for a search of the given server, limit and lowercased query.
     */
    public static function cacheKey(string $serverId, int $limit, string $query): string
    {
        return "discord:members:search:{$serverId}:{$limit}:".mb_strtolower($query);
    }
}
