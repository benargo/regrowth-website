<?php

namespace App\Actions\Boss;

use App\Events\BossChanged;
use App\Models\Boss;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Applies a partial update to a boss. Columns, image removals and image
 * order are written in one transaction, and uploaded images are added after
 * it commits. Any image change then dispatches `BossChanged`, and any change
 * broadcasts `updated` once.
 */
class UpdateBoss
{
    use AsAction;

    /**
     * @param  array{name?: string, notes?: string|null, images?: list<UploadedFile>, removed_image_ids?: list<int>, image_ids?: list<int>}  $data  The request's validated data.
     */
    public function handle(Boss $boss, array $data): void
    {
        $boss->fill(Arr::only($data, ['name', 'notes']));
        $hasColumnChanges = $boss->isDirty();
        $hasMediaChanges = Arr::hasAny($data, ['images', 'removed_image_ids', 'image_ids']);

        if (! $hasColumnChanges && ! $hasMediaChanges) {
            return;
        }

        DB::transaction(function () use ($boss, $data): void {
            Boss::withoutBroadcasting(fn () => $boss->save());

            if (Arr::exists($data, 'removed_image_ids')) {
                $boss->media()->whereKey($data['removed_image_ids'])->get()->each->delete();
            }

            if (Arr::exists($data, 'image_ids')) {
                Media::setNewOrder($data['image_ids']);
            }
        });

        foreach ($data['images'] ?? [] as $image) {
            $boss->addMedia($image)->toMediaCollection();
        }

        if ($hasMediaChanges) {
            BossChanged::dispatch($boss);
        }

        $boss->broadcastUpdated();
    }
}
