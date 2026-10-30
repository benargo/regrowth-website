<?php

namespace App\Actions\Handbook;

use App\Contracts\Actions\Handbook\StoresHandbook;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reads the ordered list of chapter slugs from the order file, uncached.
 */
class ReadChapterOrder implements StoresHandbook
{
    use AsAction;

    public function __construct(
        #[Storage(self::DISK)] protected Filesystem $disk,
    ) {}

    /**
     * @return array<int, string>
     */
    public function handle(): array
    {
        if (! $this->disk->exists(self::ORDER_FILE)) {
            return [];
        }

        $order = json_decode((string) $this->disk->get(self::ORDER_FILE), true);

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
