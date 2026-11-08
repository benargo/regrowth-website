<?php

namespace App\Jobs;

use App\Contracts\Race\RaceToSixty;
use App\Enums\Medal;
use App\Enums\RaceState;
use App\Events\Broadcasts\RaceStandingsUpdated;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Exceptions\BlizzardRequestException;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Jobs\Discord\NotifyDiscordChannel;
use App\Models\Character;
use App\Models\GameVersion;
use App\Notifications\RacePlacementClaimed;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Saloon\Exceptions\Request\FatalRequestException;
use Spatie\LaravelData\Exceptions\CannotCreateData;
use TypeError;

/**
 * Polls Blizzard for the Forever characters leading the race to level 60, raises their stored levels,
 * records placements, queues Discord announcements for medals and broadcasts a standings refresh.
 */
#[Tries(1)]
class TrackRaceToSixty implements RaceToSixty, ShouldQueue
{
    use Queueable;

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['blizzard', 'race-to-60'];
    }

    /**
     * Get the middleware the job should pass through, dropping a run that overlaps one already in progress.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('track-race-to-sixty'))->dontRelease()->expireAfter(120)];
    }

    /**
     * Execute the job.
     */
    public function handle(BlizzardConnector $blizzard, Config $config): void
    {
        $gameVersion = GameVersion::forever();

        if ($gameVersion === null) {
            return;
        }

        $bronze = $gameVersion->racePlacements()->where('position', 3)->first();

        if (RaceState::fromBronzeReachedAt($bronze?->reached_at) === RaceState::Finished) {
            return;
        }

        $unplaced = $gameVersion->characters()->whereDoesntHave('racePlacement');
        $topLevel = (clone $unplaced)->max('level');

        if ($topLevel === null) {
            $this->broadcast(hasChanges: false);

            return;
        }

        $candidates = $unplaced->where('level', '>=', (int) $topLevel - self::PACK_WINDOW)->get();

        [$lastLogins, $hasChanges] = $this->pollCandidates($blizzard, $gameVersion, $candidates);

        $finishers = $candidates
            ->filter(fn (Character $character): bool => $character->level >= self::TARGET_LEVEL)
            ->sortBy(fn (Character $character): array => [$lastLogins[$character->id] ?? PHP_INT_MAX, $character->id])
            ->values();

        if ($finishers->isNotEmpty()) {
            $this->recordPlacements($gameVersion, $finishers, $config->get('services.discord.channels.announcements'));

            $hasChanges = true;
        }

        $this->broadcast($hasChanges);
    }

    /**
     * Fetch each candidate's profile and raise its stored level without firing model events, skipping failed lookups and malformed profiles.
     *
     * @param  Collection<int, Character>  $candidates
     * @return array{0: array<int, int>, 1: bool} the last login timestamps keyed by character ID, and whether any level was raised
     */
    private function pollCandidates(BlizzardConnector $blizzard, GameVersion $gameVersion, Collection $candidates): array
    {
        $lastLogins = [];
        $hasChanges = false;

        foreach ($candidates as $character) {
            try {
                $profile = $blizzard->send(
                    (new GetCharacterProfileRequest($gameVersion->realm_slug, $character->name, $gameVersion->blizzard_namespace, cacheExpiryInSeconds: 50))
                        ->invalidateCache(),
                )->dto();
            } catch (BlizzardRequestException|FatalRequestException|CannotCreateData|TypeError) {
                continue;
            }

            $lastLogins[$character->id] = $profile->lastLoginTimestamp;

            if ($profile->level > $character->level) {
                $character->level = $profile->level;
                $character->saveQuietly();

                $hasChanges = true;
            }
        }

        return [$lastLogins, $hasChanges];
    }

    /**
     * Create the next placements in order and queue an announcement for each medal position.
     *
     * @param  Collection<int, Character>  $finishers
     */
    private function recordPlacements(GameVersion $gameVersion, Collection $finishers, ?string $announcementsChannelId): void
    {
        DB::transaction(function () use ($gameVersion, $finishers, $announcementsChannelId): void {
            $nextPosition = ((int) $gameVersion->racePlacements()->max('position')) + 1;

            foreach ($finishers as $character) {
                $placement = $gameVersion->racePlacements()->create([
                    'character_id' => $character->id,
                    'position' => $nextPosition++,
                    'reached_at' => now(),
                ]);

                if (Medal::fromPosition($placement->position) !== null) {
                    NotifyDiscordChannel::dispatch($announcementsChannelId, new RacePlacementClaimed($placement))->afterCommit();
                }
            }
        });
    }

    /**
     * Store the check time and broadcast the standings refresh signal.
     */
    private function broadcast(bool $hasChanges): void
    {
        $checkedAt = now();

        Cache::forever(self::CHECKED_AT_CACHE_KEY, $checkedAt->toIso8601String());

        RaceStandingsUpdated::dispatch($checkedAt, $hasChanges);
    }
}
