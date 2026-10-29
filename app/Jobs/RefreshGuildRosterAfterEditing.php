<?php

namespace App\Jobs;

use App\Models\GameVersion;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshGuildRosterAfterEditing implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    /**
     * How long to wait between checks of the edit lock.
     */
    public const int RECHECK_SECONDS = 60;

    /**
     * Drop the job if the game version is deleted before it runs.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Let the unique lock lapse if a pending check is lost from the queue, so
     * later edits can still schedule one. A duplicate check only costs one
     * extra roster fetch.
     */
    public int $uniqueFor = 600;

    public function __construct(public GameVersion $gameVersion) {}

    /**
     * Queue a check for after the current transaction commits.
     */
    public static function schedule(GameVersion $gameVersion): void
    {
        static::dispatch($gameVersion)
            ->delay(self::RECHECK_SECONDS)
            ->afterCommit();
    }

    /**
     * One pending check per game version.
     */
    public function uniqueId(): string
    {
        return (string) $this->gameVersion->getKey();
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['blizzard', "game-version:{$this->gameVersion->getKey()}"];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->gameVersion->isBeingEdited()) {
            static::schedule($this->gameVersion);

            return;
        }

        if (! $this->gameVersion->ownsCurrentRoster()) {
            return;
        }

        FetchGuildRoster::dispatch($this->gameVersion->id, bypassRateLimit: true);
    }
}
