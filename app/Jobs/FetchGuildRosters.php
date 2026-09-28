<?php

namespace App\Jobs;

use App\Models\GameVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchGuildRosters implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public bool $bypassRateLimit = false,
    ) {}

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['blizzard'];
    }

    /**
     * Dispatch one roster fetch per Blizzard namespace, realm and guild.
     */
    public function handle(): void
    {
        $gameVersions = GameVersion::query()
            ->whereNotNull('blizzard_namespace')
            ->where('release_date', '<=', now())
            ->orderByDesc('release_date')
            ->get()
            ->reject(fn (GameVersion $gameVersion): bool => $gameVersion->realm === null
                && $gameVersion->blizzard_namespace->requiresRealm())
            ->unique(fn (GameVersion $gameVersion): string => $this->rosterKey($gameVersion));

        foreach ($gameVersions as $gameVersion) {
            FetchGuildRoster::dispatch($gameVersion->id, $this->bypassRateLimit);
        }
    }

    /**
     * Build the key that identifies a guild roster on Blizzard's side.
     */
    private function rosterKey(GameVersion $gameVersion): string
    {
        return "{$gameVersion->blizzard_namespace->value}|{$gameVersion->realm_slug}|{$gameVersion->guild_slug}";
    }
}
