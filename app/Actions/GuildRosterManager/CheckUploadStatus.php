<?php

namespace App\Actions\GuildRosterManager;

use App\Contracts\Actions\GuildRosterManager\AssessesUploadFreshness;
use App\Data\GuildRosterManager\LatestUploadData;
use App\Enums\GuildRosterManager\UploadStatus;
use App\Models\GameVersion;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Summarises how trustworthy a game version's latest GRM upload is by
 * comparing its member rows with Blizzard's live guild member count.
 */
class CheckUploadStatus implements AssessesUploadFreshness
{
    use AsAction;

    public function __construct(
        protected FindLatestUpload $findLatestUpload,
        protected CountGuildMembers $countGuildMembers,
    ) {}

    public function handle(GameVersion $gameVersion): UploadStatus
    {
        $upload = $this->findLatestUpload->handle($gameVersion);

        if ($upload === null) {
            return UploadStatus::Missing;
        }

        $guildMemberCount = $this->countGuildMembers->handle($gameVersion);

        if ($this->isStale($guildMemberCount, $upload)) {
            return UploadStatus::Stale;
        }

        if ($upload->lastModified->lt(now()->subDays(self::OUTDATED_AFTER_DAYS))) {
            return UploadStatus::Outdated;
        }

        if ($guildMemberCount === null) {
            return UploadStatus::Unknown;
        }

        return UploadStatus::Current;
    }

    /**
     * Determine whether the guild's live member count has drifted too far from
     * the upload's. An unknown count is never stale.
     */
    protected function isStale(?int $guildMemberCount, LatestUploadData $upload): bool
    {
        if ($guildMemberCount === null) {
            return false;
        }

        return abs($guildMemberCount - $upload->memberCount) >= self::STALE_THRESHOLD;
    }
}
