<?php

namespace App\Actions\GuildRosterManager;

use App\Data\GuildRosterManager\LeaverData;
use App\Data\GuildRosterManager\MembershipEventData;
use App\Data\GuildRosterManager\RowResultData;
use App\Enums\GuildRosterManager\RowReason;
use App\Enums\GuildRosterManager\RowStatus;
use App\Models\Character;
use App\Models\GameVersion;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Marks the matched character from one committed GRM leaver row as left the guild, links its
 * memberships and bans, and returns the row's recorded outcome. It never creates a character and
 * never calls Blizzard.
 */
class SyncLeaver
{
    use AsAction;

    public function handle(
        GameVersion $gameVersion,
        LeaverData $leaver,
        CarbonInterface $syncStartedAt,
        ?CarbonInterface $evidenceAt,
        bool $isRetry = false,
    ): RowResultData {
        ['name' => $name, 'surname' => $surname] = NormaliseName::run($gameVersion, $leaver->name);
        $character = MatchCharacter::run($gameVersion, $leaver->guid, $name, $surname);

        if ($character === null) {
            return $this->result($leaver, null, RowStatus::Skipped, RowReason::NotTracked);
        }

        $departedAt = $this->departureTime($leaver, $syncStartedAt, $evidenceAt);
        $isLeft = ! $isRetry || IsNewerEvidence::run($departedAt, $character->rank_observed_at);

        $character->grm_guid = $leaver->guid;

        if ($isLeft) {
            $character->left_guild_at = $departedAt;
        }

        DB::transaction(function () use ($character, $leaver): void {
            Character::withoutEvents(fn (): bool => $character->save());
            SyncGuildMemberships::run($character, $leaver->history);
            LinkBansToCharacter::run($character);
        });

        return $this->result($leaver, $character->id, RowStatus::Processed, $isLeft ? RowReason::Left : RowReason::Updated);
    }

    /**
     * The evidence time, else the start of the newest leave event's day, else the sync start time,
     * in the application timezone.
     */
    private function departureTime(LeaverData $leaver, CarbonInterface $syncStartedAt, ?CarbonInterface $evidenceAt): CarbonInterface
    {
        $timezone = config('app.timezone');

        if ($evidenceAt !== null) {
            return $evidenceAt->copy()->setTimezone($timezone);
        }

        $newestLeave = collect($leaver->history)
            ->filter(fn (MembershipEventData $event): bool => $event->type === 'left')
            ->max('date');

        if ($newestLeave !== null) {
            return Carbon::parse($newestLeave, $timezone)->startOfDay();
        }

        return $syncStartedAt->copy()->setTimezone($timezone);
    }

    /**
     * Build a result for the leaver's row.
     */
    private function result(LeaverData $leaver, ?int $characterId, RowStatus $status, RowReason $reason): RowResultData
    {
        return new RowResultData($characterId, $leaver->guid, $leaver->name, $status, $reason);
    }
}
