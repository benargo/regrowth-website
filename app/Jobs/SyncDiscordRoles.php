<?php

namespace App\Jobs;

use App\Models\DiscordRole;
use App\Services\Discord\Discord;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncDiscordRoles implements ShouldQueue
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
            new WithoutOverlapping,
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(Discord $discord): void
    {
        $roles = $discord->getGuildRoles()->filter(fn ($role) => $role->position !== 0);

        $syncedIds = $roles->map(fn ($role) => (string) $role->id)->values()->all();

        DB::transaction(function () use ($roles, $syncedIds) {
            $deleted = DiscordRole::whereNotIn('id', $syncedIds)->delete();

            foreach ($roles as $role) {
                DiscordRole::updateOrCreate(
                    ['id' => (string) $role->id],
                    ['name' => $role->name, 'position' => $role->position],
                );
            }

            Log::info('Synchronising Discord roles job completed.', [
                'synced' => count($roles),
                'deleted' => $deleted,
            ]);
        });
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Synchronising Discord roles job failed.', [
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
        return ['discord', 'discord:roles'];
    }
}
