<?php

namespace App\Actions\GuildRosterManager;

use App\Data\GuildRosterManager\LatestUploadData;
use App\Enums\GuildRosterManager\UploadStatus;
use App\Models\GameVersion;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Summarises how trustworthy a game version's latest GRM upload is by
 * comparing its member rows with Blizzard's live guild member count.
 */
class CheckUploadStatus
{
    use AsAction;

    /**
     * The member-count difference at which an upload counts as stale.
     */
    public const int STALE_THRESHOLD = CheckUploadFreshness::STALE_THRESHOLD;

    /**
     * The age at which an upload counts as outdated.
     */
    public const int OUTDATED_AFTER_DAYS = 7;

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
