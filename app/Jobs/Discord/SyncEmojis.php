<?php

namespace App\Jobs\Discord;

use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildEmojisRequest;
use App\Jobs\Middleware\ReleaseWhenDiscordRateLimited;
use App\Models\DiscordEmoji;
use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelData\Optional;
use Throwable;

#[MaxExceptions(3)]
#[Backoff(60)]
class SyncEmojis implements ShouldQueue
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
            (new WithoutOverlapping)->dontRelease(),
            new ReleaseWhenDiscordRateLimited,
        ];
    }

    /**
     * Sync the guild's emoji, deleting any no longer in Discord.
     */
    public function handle(DiscordConnector $discord, #[Config('services.discord.server_id')] string $serverId): void
    {
        $emoji = $discord->send(new GetGuildEmojisRequest($serverId))->dto();

        $syncedIds = $emoji->pluck('id')->all();

        DB::transaction(function () use ($emoji, $syncedIds): void {
            DiscordEmoji::whereNotIn('id', $syncedIds)->delete();

            foreach ($emoji as $item) {
                DiscordEmoji::updateOrCreate(
                    ['id' => $item->id],
                    [
                        'name' => $item->name,
                        'animated' => $item->animated instanceof Optional ? false : $item->animated,
                        'available' => $item->available instanceof Optional ? true : $item->available,
                    ],
                );
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Synchronising Discord emoji job failed.', [
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['discord', 'discord:emoji'];
    }
}
