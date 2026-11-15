<?php

namespace App\Jobs;

use App\Actions\Characters\AssignPlayableClassAndRace;
use App\Actions\Characters\MarkGuildMembership;
use App\Actions\GameVersion\LinkRosterClassesAndRaces;
use App\Actions\GuildRosterManager\IsNewerEvidence;
use App\Contracts\Http\Integrations\Blizzard\RequiresMinimumCharacterLevel;
use App\Enums\Gender;
use App\Enums\RankSource;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterMemberData;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Saloon\Http\Response;
use Throwable;

class FetchGuildRoster implements RequiresMinimumCharacterLevel, ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(
        public int $gameVersionId,
        public bool $bypassRateLimit = false,
    ) {}

    /**
     * Get the middleware the job should pass through. Deliberate refreshes
     * bypass the hourly limit so they are never silently dropped.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        if ($this->bypassRateLimit) {
            return [];
        }

        return [
            (new RateLimitedWithRedis('fetch-guild-roster-job'))->dontRelease(),
        ];
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['blizzard', "game-version:{$this->gameVersionId}"];
    }

    /**
     * Execute the job.
     */
    public function handle(BlizzardConnector $blizzard): void
    {
        $gameVersion = GameVersion::findOrFail($this->gameVersionId);

        $guildRanks = $gameVersion->guildRanks()->get()->keyBy('sort_order');

        if ($guildRanks->isEmpty()) {
            Log::warning('Skipped guild roster sync: the game version has no guild ranks.', [
                'game_version_id' => $gameVersion->id,
            ]);

            return;
        }

        $response = $this->fetchRoster($blizzard, $gameVersion);
        $roster = $response->dto();
        $observedAt = $this->lastModified($response, $gameVersion);

        LinkRosterClassesAndRaces::run($gameVersion, $roster->members);

        $assignPlayableClassAndRace = AssignPlayableClassAndRace::make();

        foreach ($roster->members as $member) {
            try {
                $this->syncCharacter($blizzard, $gameVersion, $guildRanks, $member, $assignPlayableClassAndRace, $observedAt);
            } catch (Throwable $e) {
                Log::warning('Failed to sync character from guild roster.', [
                    'character_id' => $member->character->id,
                    'character_name' => $member->character->fullName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($observedAt !== null) {
            MarkGuildMembership::run(
                $gameVersion,
                collect($roster->members)->map(fn (GuildRosterMemberData $member): int => $member->character->id),
                $observedAt,
            );
        }
    }

    /**
     * Fetch the game version's guild roster from the Blizzard API.
     */
    private function fetchRoster(BlizzardConnector $blizzard, GameVersion $gameVersion): Response
    {
        return $blizzard->send(new GetGuildRosterRequest(
            $gameVersion->realm_slug,
            $gameVersion->guild_slug,
            $gameVersion->blizzard_namespace,
        ));
    }

    /**
     * Read the time the roster data was last modified from the response's Last-Modified header,
     * or null (with a warning) when the header is missing or unparsable.
     */
    private function lastModified(Response $response, GameVersion $gameVersion): ?CarbonImmutable
    {
        $header = $response->header('Last-Modified');
        $value = is_array($header) ? ($header[0] ?? null) : $header;
        $lastModified = null;

        if (is_string($value)) {
            try {
                $lastModified = CarbonImmutable::createFromFormat(DATE_RFC7231, $value, 'UTC') ?: null;
            } catch (InvalidFormatException) {
                $lastModified = null;
            }
        }

        if ($lastModified === null) {
            Log::warning('Skipped rank, level and membership from the guild roster: the response has no usable Last-Modified header.', [
                'game_version_id' => $gameVersion->id,
                'cached' => $response->isCached(),
            ]);

            return null;
        }

        return $lastModified;
    }

    /**
     * Sync a single character from the guild roster data.
     *
     * @param  Collection<int, GuildRank>  $guildRanks  The game version's ranks, keyed by sort order.
     * @param  ?CarbonInterface  $observedAt  When Blizzard last modified the roster; null writes no rank or level evidence.
     */
    private function syncCharacter(BlizzardConnector $blizzard, GameVersion $gameVersion, Collection $guildRanks, GuildRosterMemberData $member, AssignPlayableClassAndRace $assignPlayableClassAndRace, ?CarbonInterface $observedAt): void
    {
        if ($member->character->level < self::MIN_LEVEL) {
            return;
        }

        $guildRank = $guildRanks->get($member->rank)
            ?? throw (new ModelNotFoundException)->setModel(GuildRank::class, [$member->rank]);

        $characterDto = $blizzard->send(new GetCharacterProfileRequest(
            $gameVersion->realm_slug,
            $member->character->nameForVersion($gameVersion->uses_surnames),
            $gameVersion->blizzard_namespace,
        ))->dto();

        $character = Character::firstOrNew(['id' => $member->character->id]);
        $character->fill([
            'name' => $member->character->name,
            'surname' => $member->character->surnameForVersion($gameVersion->uses_surnames),
            'gender' => Gender::tryFrom(data_get($characterDto, 'gender.name')),
            'game_version_id' => $gameVersion->id,
        ]);

        $evidenceAt = $observedAt?->setTimezone(config('app.timezone'));
        $isNew = ! $character->exists;

        if ($isNew) {
            $character->level = $member->character->level;
            $character->rank()->associate($guildRank);

            if ($evidenceAt !== null) {
                $character->fill([
                    'rank_observed_at' => $evidenceAt,
                    'rank_source' => RankSource::Blizzard,
                    'level_observed_at' => $evidenceAt,
                ]);
            }
        } else {
            $isNewer = IsNewerEvidence::make();

            if ($isNewer->handle($observedAt, $character->level_observed_at)) {
                $character->fill([
                    'level' => max($character->level ?? 0, $member->character->level),
                    'level_observed_at' => $evidenceAt,
                ]);
            }

            if ($isNewer->handle($observedAt, $character->rank_observed_at)) {
                $character->rank()->associate($guildRank);
                $character->fill([
                    'rank_observed_at' => $evidenceAt,
                    'rank_source' => RankSource::Blizzard,
                ]);
            }
        }

        $assignPlayableClassAndRace->handle($character, $gameVersion, $characterDto->characterClass->id, $characterDto->race->id);

        Character::withoutEvents(function () use ($character) {
            $character->save();
        });
    }
}
