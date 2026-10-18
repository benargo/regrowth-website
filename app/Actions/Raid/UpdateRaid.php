<?php

namespace App\Actions\Raid;

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
 * transaction with any columns, then flushes the raiding cache once that
 * transaction has committed. `banner` replaces the banner (null removes it)
 * and `banner_position` sets the banner's `position` custom property. A new
 * banner keeps the previous position unless a position comes with it. Media
 * is written once the database changes have committed.
 */
class UpdateRaid
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
        $columns = Arr::except($data, self::NON_COLUMN_KEYS);

        if (Arr::exists($data, 'boss_ids')) {
            DB::transaction(function () use ($raid, $columns, $data): void {
                $raid->update($columns);
                Boss::setNewOrder($data['boss_ids']);
            });

            Raid::flushRaidingCache();
        } elseif ($columns !== []) {
            $raid->update($columns);
        }

        if (Arr::exists($data, 'banner')) {
            $position = Arr::exists($data, 'banner_position') ? $data['banner_position'] : $raid->banner_position;
            $this->replaceBanner($raid, $data['banner'], $position);

            return;
        }

        if (Arr::exists($data, 'banner_position')) {
            $this->positionBanner($raid->getFirstMedia(Raid::MEDIA_BANNER), $data['banner_position']);
        }
    }

    /**
     * Store the uploaded banner with the given position, replacing the
     * previous one, or remove the banner when no file is given.
     */
    private function replaceBanner(Raid $raid, ?UploadedFile $file, ?string $position): void
    {
        if ($file === null) {
            $raid->clearMediaCollection(Raid::MEDIA_BANNER);

            return;
        }

        $raid->addMedia($file)
            ->withCustomProperties($position === null ? [] : ['position' => $position])
            ->toMediaCollection(Raid::MEDIA_BANNER);
    }

    /**
     * Set or clear the banner's position. Does nothing without a banner.
     */
    private function positionBanner(?Media $banner, ?string $position): void
    {
        if ($banner === null) {
            return;
        }

        if ($position === null) {
            $banner->forgetCustomProperty('position');
        } else {
            $banner->setCustomProperty('position', $position);
        }

        $banner->save();
    }
}
