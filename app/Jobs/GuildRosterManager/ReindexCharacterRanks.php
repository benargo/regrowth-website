<?php

namespace App\Jobs\GuildRosterManager;

use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Enums\RankSource;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\GuildRosterManager\RosterEntry;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;

/**
 * Points each of a game version's characters at the guild rank their member roster entry names by rank
 * index, after the version's ranks were added or removed. A character is left alone when it has no
 * rank for the index, already holds that rank, or holds a rank with evidence newer than the entry's
 * epoch. A character without a rank is always given the entry's rank.
 * Changed characters are saved as GRM-sourced ranks, and the `attendance` and `characters` caches are
 * flushed if any changed. It shares the `grm-sync` lock with `SyncRoster`.
 */
#[Tries(0)]
#[MaxExceptions(3)]
#[Backoff(60)]
#[Timeout(900)]
final class ReindexCharacterRanks implements ShouldQueue
{
    use Queueable;

    private const int CHUNK_SIZE = 500;

    /**
     * @param  int  $gameVersionId  The game version whose characters are re-pointed.
     */
    public function __construct(public readonly int $gameVersionId) {}

    /**
     * Get the time after which the job is no longer attempted.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }

    /**
     * Get the middleware the job passes through: one sync job per game version at a time, shared with `SyncRoster`.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("grm-sync:{$this->gameVersionId}"))->shared()->releaseAfter(60)->expireAfter(960)];
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['grm-sync', "game-version:{$this->gameVersionId}"];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        /** @var Collection<int, GuildRank> $ranks */
        $ranks = GuildRank::where('game_version_id', $this->gameVersionId)->get()->keyBy('sort_order');
        $hasChanged = false;

        RosterEntry::where('game_version_id', $this->gameVersionId)
            ->where('kind', RosterEntryKind::Member)
            ->chunkById(self::CHUNK_SIZE, function ($entries) use ($ranks, &$hasChanged): void {
                $characters = Character::where('game_version_id', $this->gameVersionId)
                    ->whereIn('grm_guid', $entries->pluck('key'))
                    ->get()
                    ->keyBy('grm_guid');

                foreach ($entries as $entry) {
                    $character = $characters->get($entry->key);
                    $rank = $ranks->get($entry->payload->rank_index);

                    if ($character === null || $rank === null || $character->rank_id === $rank->id) {
                        continue;
                    }

                    $evidenceAt = $entry->epoch > 0
                        ? CarbonImmutable::createFromTimestampUTC($entry->epoch)->setTimezone(config('app.timezone'))
                        : null;

                    if ($character->rank_id !== null && $character->rank_observed_at !== null && ($evidenceAt === null || $character->rank_observed_at->gt($evidenceAt))) {
                        continue;
                    }

                    $character->fill([
                        'rank_id' => $rank->id,
                        'rank_observed_at' => $evidenceAt,
                        'rank_source' => RankSource::Grm,
                    ]);

                    Character::withoutEvents(fn () => $character->save());
                    $hasChanged = true;
                }
            });

        if ($hasChanged) {
            Cache::tags(['attendance'])->flush();
            Cache::tags(['characters'])->flush();
        }
    }
}
