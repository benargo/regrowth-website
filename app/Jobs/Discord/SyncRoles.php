<?php

namespace App\Jobs\Discord;

use App\Http\Integrations\Discord\Data\Guilds\RoleData;
use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildRolesRequest;
use App\Jobs\Middleware\ReleaseWhenDiscordRateLimited;
use App\Models\DiscordRole;
use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

#[MaxExceptions(3)]
#[Backoff(60)]
class SyncRoles implements ShouldQueue
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
     * Sync the guild's roles, deleting any no longer in Discord and skipping @everyone.
     */
    public function handle(DiscordConnector $discord, #[Config('services.discord.server_id')] string $serverId): void
    {
        $roles = $discord->send(new GetGuildRolesRequest($serverId))
            ->dto()
            ->filter(fn (RoleData $role): bool => $role->position !== 0);

        $syncedIds = $roles->map(fn (RoleData $role): string => $role->id)->values()->all();

        DB::transaction(function () use ($roles, $syncedIds): void {
            $deleted = DiscordRole::whereNotIn('id', $syncedIds)->delete();

            foreach ($roles as $role) {
                DiscordRole::updateOrCreate(
                    ['id' => $role->id],
                    ['name' => $role->name, 'position' => $role->position],
                );
            }

            Log::info('Synchronising Discord roles job completed.', [
                'synced' => count($roles),
                'deleted' => $deleted,
            ]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Synchronising Discord roles job failed.', [
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['discord', 'discord:roles'];
    }
}
