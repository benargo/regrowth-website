<?php

namespace App\Data\GuildRosterManager;

use App\Actions\GuildRosterManager\CheckUploadStatus;
use App\Actions\GuildRosterManager\CompareRankNames;
use App\Data\Transformers\ToResourceTransformer;
use App\Enums\GuildRosterManager\UploadStatus;
use App\Http\Resources\GameVersionResource;
use App\Http\Resources\GuildRosterManager\UploadResource;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\Upload;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * A roster's freshness: its game version (icon scope), upload status, newest accepted upload, and the
 * rank names that differ between the game and the site.
 */
final class RosterFreshnessData extends Data
{
    /**
     * The game version's upload status.
     */
    #[Computed]
    public readonly UploadStatus $status;

    /**
     * The game version's newest accepted upload, with its device and uploader, or null when there is none.
     */
    #[Computed]
    #[MapOutputName(CamelCaseMapper::class)]
    #[WithTransformer(ToResourceTransformer::class, resource: UploadResource::class)]
    public readonly ?Upload $lastUpload;

    /**
     * The ranks whose names differ between the game and the site.
     *
     * @var list<array{sortOrder: int, gameName: ?string, siteName: ?string}>
     */
    #[Computed]
    #[MapOutputName(CamelCaseMapper::class)]
    public readonly array $rankNameDifferences;

    public function __construct(
        #[MapOutputName(CamelCaseMapper::class)]
        #[WithTransformer(ToResourceTransformer::class, resource: GameVersionResource::class, scope: 'icon')]
        public readonly GameVersion $gameVersion,
    ) {
        $this->status = CheckUploadStatus::run($gameVersion);
        $this->lastUpload = Upload::with(['device', 'user'])
            ->where('game_version_id', $gameVersion->id)
            ->accepted()
            ->latest('captured_at')
            ->first();
        $this->rankNameDifferences = CompareRankNames::run($gameVersion);
    }

    /**
     * Describe the given game version's roster freshness.
     */
    public static function fromGameVersion(GameVersion $gameVersion): self
    {
        return new self($gameVersion);
    }
}
