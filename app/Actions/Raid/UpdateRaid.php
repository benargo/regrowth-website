<?php

namespace App\Actions\Raid;

use App\Contracts\Models\SharesRaidBannerCollection;
use App\Events\RaidingDataChanged;
use App\Models\Boss;
use App\Models\Raid;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Applies a partial update to a raid. Only the keys present in the validated
 * data are touched, and a key given as null clears its column.
 *
 * `boss_ids` stores the raid's boss order, the first id first, in one
 * transaction with any columns. `banner` replaces the banner (null removes it)
 * and `banner_position` sets the banner's `position` custom property. A new
 * banner keeps the previous position unless a position comes with it. Media
 * is written once the database changes have committed. Any change to the boss
 * order or banner then dispatches `RaidingDataChanged`.
 */
class UpdateRaid implements SharesRaidBannerCollection
{
    use AsAction;

    /**
     * Validated keys that are not raid columns.
     *
     * @var list<string>
     */
    private const array NON_COLUMN_KEYS = ['banner', 'banner_position', 'boss_ids'];

    /**
     * @param  array<string, mixed>  $data  The request's validated data.
     */
    public function handle(Raid $raid, array $data): void
    {
        $this->updateColumns($raid, Arr::except($data, self::NON_COLUMN_KEYS), Arr::get($data, 'boss_ids'));
        $this->updateBanner($raid, $data);

        if (Arr::hasAny($data, self::NON_COLUMN_KEYS)) {
            RaidingDataChanged::dispatch($raid);
        }
    }

    /**
     * Write the given columns, together with the boss order in one transaction
     * when boss ids are given.
     *
     * @param  array<string, mixed>  $columns
     * @param  ?list<int>  $bossIds
     */
    private function updateColumns(Raid $raid, array $columns, ?array $bossIds): void
    {
        if ($bossIds !== null) {
            DB::transaction(function () use ($raid, $columns, $bossIds): void {
                $raid->update($columns);
                Boss::setNewOrder($bossIds);
            });

            return;
        }

        if ($columns === []) {
            return;
        }

        $raid->update($columns);
    }

    /**
     * Replace the banner when `banner` is present, otherwise reposition it when
     * `banner_position` is present.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateBanner(Raid $raid, array $data): void
    {
        if (Arr::exists($data, 'banner')) {
            $position = Arr::exists($data, 'banner_position') ? $data['banner_position'] : $raid->banner_position;
            $this->replaceBanner($raid, $data['banner'], $position);

            return;
        }

        if (! Arr::exists($data, 'banner_position')) {
            return;
        }

        $this->positionBanner($raid->getFirstMedia(self::MEDIA_BANNER), $data['banner_position']);
    }

    /**
     * Store the uploaded banner at the given position, replacing the old one, or remove it when no file is given.
     */
    private function replaceBanner(Raid $raid, ?UploadedFile $file, ?string $position): void
    {
        if ($file === null) {
            $raid->clearMediaCollection(self::MEDIA_BANNER);

            return;
        }

        $raid->addMedia($file)
            ->withCustomProperties($position === null ? [] : ['position' => $position])
            ->toMediaCollection(self::MEDIA_BANNER);
    }

    /**
     * Set or clear the banner's position. Does nothing without a banner.
     */
    private function positionBanner(?Media $banner, ?string $position): void
    {
        if ($banner === null) {
            return;
        }

        $position === null
            ? $banner->forgetCustomProperty('position')
            : $banner->setCustomProperty('position', $position);

        $banner->save();
    }
}
