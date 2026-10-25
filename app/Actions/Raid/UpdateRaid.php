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
 * Columns, the boss order (`boss_ids`, the first id first) and the banner's
 * `position` custom property (`banner_position` without `banner`) are written
 * in one transaction. `banner` then replaces the banner (null removes it),
 * keeping the previous position unless a position comes with it. When the
 * boss order or banner position changes without a column change,
 * `RaidingDataChanged` is dispatched.
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
        $raid->fill(Arr::except($data, self::NON_COLUMN_KEYS));
        $hasColumnChanges = $raid->isDirty();
        $reordersBosses = Arr::exists($data, 'boss_ids');
        $positionsBanner = Arr::exists($data, 'banner_position') && ! Arr::exists($data, 'banner');

        if ($hasColumnChanges || $reordersBosses || $positionsBanner) {
            DB::transaction(function () use ($raid, $data, $reordersBosses, $positionsBanner): void {
                $raid->save();

                if ($reordersBosses) {
                    Boss::setNewOrder($data['boss_ids']);
                }

                if ($positionsBanner) {
                    $this->positionBanner($raid->getFirstMedia(self::MEDIA_BANNER), $data['banner_position']);
                }
            });
        }

        if (Arr::exists($data, 'banner')) {
            $position = Arr::exists($data, 'banner_position') ? $data['banner_position'] : $raid->banner_position;
            $this->replaceBanner($raid, $data['banner'], $position);
        }

        if (! $hasColumnChanges && ($reordersBosses || $positionsBanner)) {
            RaidingDataChanged::dispatch($raid);
        }
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
