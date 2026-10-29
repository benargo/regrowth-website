<?php

namespace App\Jobs\Discord;

use App\Actions\Discord\FetchChannel;
use App\Actions\Discord\UpsertDiscordChannel;
use App\Http\Integrations\Discord\Data\Channels\ChannelData;
use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Enums\ChannelType;
use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Exceptions\ChannelNotFoundException;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildChannelsRequest;
use App\Jobs\Middleware\ReleaseWhenDiscordRateLimited;
use App\Models\DiscordChannel;
use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

#[MaxExceptions(3)]
#[Backoff(60)]
class SyncChannels implements ShouldQueue
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
     * Sync the guild's channels, refresh stored threads and stubs by ID, and soft-delete channels Discord no longer returns.
     */
    public function handle(DiscordConnector $discord, #[Config('services.discord.server_id')] string $serverId): void
    {
        /** @var Collection<int, ChannelData> $guildChannels */
        $guildChannels = $discord->send(new GetGuildChannelsRequest($serverId))->dto();
        $guildIds = $guildChannels->pluck('id')->all();

        $fetchIds = DiscordChannel::withTrashed()->threads()->pluck('id')
            ->merge(DiscordChannel::withTrashed()->stubs()->pluck('id'))
            ->diff($guildIds)
            ->unique()
            ->values();

        $fetchedChannels = collect();
        $notFoundIds = [];

        foreach ($fetchIds as $channelId) {
            try {
                $fetchedChannels->push(FetchChannel::run($channelId, fresh: true));
            } catch (ChannelNotFoundException) {
                $notFoundIds[] = $channelId;
            } catch (ApiException $exception) {
                if ($exception->getStatus() !== 403) {
                    throw $exception;
                }

                Log::warning('Skipped a Discord channel the bot cannot access.', ['channel_id' => $channelId]);
            }
        }

        DB::transaction(function () use ($guildChannels, $guildIds, $fetchedChannels, $notFoundIds): void {
            $deleted = DiscordChannel::whereNotIn('type', ChannelType::threadTypes())
                ->whereNotIn('id', $guildIds)
                ->delete();

            $deleted += DiscordChannel::whereIn('id', $notFoundIds)->delete();

            $upsert = UpsertDiscordChannel::make();

            foreach ($guildChannels->concat($fetchedChannels) as $channel) {
                $upsert->handle($channel);
            }

            Log::info('Synchronising Discord channels job completed.', [
                'synced' => count($guildChannels),
                'threads' => count($fetchedChannels),
                'deleted' => $deleted,
            ]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Synchronising Discord channels job failed.', [
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['discord', 'discord:channels'];
    }
}
