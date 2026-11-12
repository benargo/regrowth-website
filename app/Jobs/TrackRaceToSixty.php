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
use App\Models\RacePlacement;
use App\Notifications\RacePlacementClaimed;
use Carbon\Carbon;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
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
#[Timeout(300)]
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
        return [(new WithoutOverlapping('track-race-to-sixty'))->dontRelease()->expireAfter(360)];
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
            $closesAt = $bronze->reached_at->copy()->addHours(self::HONOURABLE_MENTION_HOURS);
            $previousCheck = Cache::get(self::CHECKED_AT_CACHE_KEY);

            if ($previousCheck === null || Carbon::parse($previousCheck)->lt($closesAt)) {
                $this->broadcast(hasChanges: true);
            }

            return;
        }

        $candidates = $gameVersion->racePack(self::PACK_WINDOW)->get();

        $hasChanges = $this->pollCandidates($blizzard, $gameVersion, $candidates);

        $finishers = $candidates
            ->filter(fn (Character $character): bool => $character->level >= self::TARGET_LEVEL)
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
     * @return bool whether any level was raised
     */
    private function pollCandidates(BlizzardConnector $blizzard, GameVersion $gameVersion, Collection $candidates): bool
    {
        $hasChanges = false;

        foreach ($candidates as $character) {
            try {
                $profile = $blizzard->send(
                    (new GetCharacterProfileRequest($gameVersion->realm_slug, $character->full_name, $gameVersion->blizzard_namespace, cacheExpiryInSeconds: 50))
                        ->invalidateCache(),
                )->dto();
            } catch (BlizzardRequestException|FatalRequestException|CannotCreateData|TypeError) {
                continue;
            }

            if ($profile->level > $character->level) {
                $character->level = $profile->level;
                $character->saveQuietly();

                $hasChanges = true;
            }
        }

        return $hasChanges;
    }

    /**
     * Place every finisher at the next shared position and queue one announcement when that position is a medal.
     *
     * @param  Collection<int, Character>  $finishers
     */
    private function recordPlacements(GameVersion $gameVersion, Collection $finishers, ?string $announcementsChannelId): void
    {
        DB::transaction(function () use ($gameVersion, $finishers, $announcementsChannelId): void {
            $position = ((int) $gameVersion->racePlacements()->max('position')) + 1;
            $reachedAt = now();

            $placements = $finishers->map(fn (Character $character): RacePlacement => $gameVersion->racePlacements()->create([
                'character_id' => $character->id,
                'position' => $position,
                'reached_at' => $reachedAt,
            ]));

            if (Medal::fromPosition($position) !== null) {
                NotifyDiscordChannel::dispatch($announcementsChannelId, new RacePlacementClaimed($placements))->afterCommit();
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
