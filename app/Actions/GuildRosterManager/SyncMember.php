<?php

namespace App\Actions\GuildRosterManager;

use App\Contracts\Http\Integrations\Blizzard\RequiresMinimumCharacterLevel;
use App\Data\GuildRosterManager\MemberData;
use App\Data\GuildRosterManager\RowResultData;
use App\Enums\GuildRosterManager\RowReason;
use App\Enums\GuildRosterManager\RowStatus;
use App\Enums\RankSource;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Data\Characters\CharacterProfileData;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Http\Integrations\Blizzard\Support\NameSlug;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Saloon\Exceptions\SaloonException;

/**
 * Updates the matched character from one committed GRM member row, or creates it once Blizzard
 * lists it in the guild, writing GRM data without a Blizzard gate, and returns the row's recorded
 * outcomes.
 */
class SyncMember implements RequiresMinimumCharacterLevel
{
    use AsAction;

    /**
     * Matches the temporary name Blizzard gives a character after a forced rename.
     */
    public const string PLACEHOLDER_NAME = '/^Player[A-Z]{6}$/';

    public function __construct(private readonly BlizzardConnector $blizzard) {}

    /**
     * @param  Collection<int, GuildRank>  $ranksBySortOrder
     * @return list<RowResultData>
     */
    public function handle(
        GameVersion $gameVersion,
        MemberData $member,
        Collection $ranksBySortOrder,
        CarbonInterface $syncStartedAt,
        ?CarbonInterface $evidenceAt,
        bool $isRetry = false,
    ): array {
        if ($member->level < self::MIN_LEVEL) {
            return [$this->result($member, null, RowStatus::Skipped, RowReason::BelowMinimumLevel)];
        }

        $syncStartedAt = $syncStartedAt->copy()->setTimezone(config('app.timezone'));
        $evidenceAt = $evidenceAt?->copy()->setTimezone(config('app.timezone'));

        ['name' => $name, 'surname' => $surname] = NormaliseName::run($gameVersion, $member->name);
        $character = MatchCharacter::run($gameVersion, $member->guid, $name, $surname);

        if ($character === null) {
            $profile = $this->findGuildProfile($gameVersion, trim("{$name} {$surname}"));

            if (is_string($profile)) {
                return [$this->result($member, null, RowStatus::Warning, RowReason::NotConfirmedInGuild, $profile)];
            }

            $character = Character::firstOrNew(['id' => $profile->id]);
        }

        $isNew = ! $character->exists;
        $wasLeft = $character->left_guild_at !== null;
        $oldFullName = $character->full_name;
        $newFullName = trim("{$name} {$surname}");
        $isPlaceholder = ! $isNew && $oldFullName !== $newFullName && $surname === null && preg_match(self::PLACEHOLDER_NAME, $name) === 1;
        $isRenamed = ! $isNew && ! $isPlaceholder && $oldFullName !== $newFullName;

        if (! $isPlaceholder) {
            $character->fill(['name' => $name, 'surname' => $surname]);
        }

        $character->fill(['grm_guid' => $member->guid, 'game_version_id' => $gameVersion->id]);

        $playableClass = ResolvePlayableClass::run($gameVersion, $member->class);
        $playableRace = ResolvePlayableRace::run($gameVersion, $member->race);
        $rank = $ranksBySortOrder->get($member->rank_index);

        if ($playableClass !== null) {
            $character->playable_class_id = $playableClass->id;
        }

        if ($playableRace !== null) {
            $character->playable_race_id = $playableRace->id;
        }

        $this->fillEvidenceBackedFields($character, $member, $rank, $syncStartedAt, $evidenceAt, $isRetry);

        try {
            DB::transaction(function () use ($character, $member): void {
                Character::withoutEvents(fn (): bool => $character->save());
                SyncGuildMemberships::run($character, $member->history);
                LinkBansToCharacter::run($character);
            });
        } catch (UniqueConstraintViolationException) {
            return [$this->result(
                $member,
                $isNew ? null : $character->id,
                RowStatus::Failed,
                RowReason::NameClash,
                "{$newFullName} already belongs to another character",
            )];
        }

        $reason = match (true) {
            $isNew => RowReason::Created,
            $isRenamed => RowReason::Renamed,
            $wasLeft && $character->left_guild_at === null => RowReason::Rejoined,
            default => RowReason::Updated,
        };

        $results = [$this->result($member, $character->id, RowStatus::Processed, $reason, $isRenamed ? "{$oldFullName} → {$newFullName}" : null)];

        if ($playableClass === null) {
            $results[] = $this->result($member, $character->id, RowStatus::Warning, RowReason::UnknownClass, $member->class);
        }

        if ($playableRace === null) {
            $results[] = $this->result($member, $character->id, RowStatus::Warning, RowReason::UnknownRace, $member->race);
        }

        if ($rank === null) {
            $results[] = $this->result($member, $character->id, RowStatus::Warning, RowReason::UnknownRank, (string) $member->rank_index);
        }

        if ($isPlaceholder) {
            $results[] = $this->result($member, $character->id, RowStatus::Warning, RowReason::PlaceholderName, "{$oldFullName} → {$newFullName}");
        }

        return $results;
    }

    /**
     * Fill the level, membership and rank. A retry row writes each only when its evidence is newer
     * than the stored evidence.
     */
    private function fillEvidenceBackedFields(
        Character $character,
        MemberData $member,
        ?GuildRank $rank,
        CarbonInterface $syncStartedAt,
        ?CarbonInterface $evidenceAt,
        bool $isRetry,
    ): void {
        if (! $isRetry || $this->isNewer($syncStartedAt, $character->level_observed_at)) {
            $character->fill([
                'level' => max($character->level ?? 0, $member->level),
                'level_observed_at' => $syncStartedAt,
            ]);
        }

        if (! $isRetry || $this->isNewer($evidenceAt, $character->left_guild_at)) {
            $character->left_guild_at = null;
        }

        if ($rank !== null && (! $isRetry || $this->isNewer($evidenceAt, $character->rank_observed_at))) {
            $character->fill([
                'rank_id' => $rank->id,
                'rank_observed_at' => $evidenceAt,
                'rank_source' => RankSource::Grm,
            ]);
        }
    }

    /**
     * Whether the evidence time is later than the stored one. A null stored time is older than
     * any evidence, and null evidence is newer than nothing.
     */
    private function isNewer(?CarbonInterface $evidenceAt, ?CarbonInterface $storedAt): bool
    {
        if ($evidenceAt === null) {
            return false;
        }

        return $storedAt === null || $evidenceAt->greaterThan($storedAt);
    }

    /**
     * Fetch the character's uncached Blizzard profile, returning it when Blizzard lists the
     * character in the version's guild, otherwise the reason it is not yet confirmed.
     */
    private function findGuildProfile(GameVersion $gameVersion, string $fullName): CharacterProfileData|string
    {
        $prefix = 'not yet confirmed in guild by Blizzard';

        try {
            $profile = $this->blizzard->send(
                (new GetCharacterProfileRequest($gameVersion->realm_slug, $fullName, $gameVersion->blizzard_namespace))->disableCaching(),
            )->dto();
        } catch (SaloonException $exception) {
            return "{$prefix}: {$exception->getMessage()}";
        }

        $guildName = $profile->guild instanceof LinkData && is_string($profile->guild->name) ? $profile->guild->name : null;

        if ($guildName === null) {
            return "{$prefix}: no guild";
        }

        if (NameSlug::from($guildName) !== $gameVersion->guild_slug) {
            return "{$prefix}: {$guildName}";
        }

        return $profile;
    }

    /**
     * Build a result for the member's row.
     */
    private function result(MemberData $member, ?int $characterId, RowStatus $status, RowReason $reason, ?string $message = null): RowResultData
    {
        return new RowResultData($characterId, $member->guid, $member->name, $status, $reason, $message);
    }
}
