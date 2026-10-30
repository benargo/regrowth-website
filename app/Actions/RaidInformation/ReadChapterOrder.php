<?php

namespace App\Actions\RaidInformation;

use App\Contracts\Actions\RaidInformation\StoresRaidInformation;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reads the ordered list of chapter slugs from the order file, uncached.
 */
class ReadChapterOrder implements StoresRaidInformation
{
    use AsAction;

    /**
     * @return array<int, string>
     */
    public function handle(): array
    {
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists(self::ORDER_FILE)) {
            return [];
        }

        $order = json_decode((string) $disk->get(self::ORDER_FILE), true);

        if (! is_array($order) || ! array_is_list($order)) {
            return [];
        }

        foreach ($order as $slug) {
            if (! is_string($slug)) {
                return [];
            }
        }

        return $order;
    }
}
