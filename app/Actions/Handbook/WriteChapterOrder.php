<?php

namespace App\Actions\Handbook;

use App\Contracts\Actions\Handbook\StoresHandbook;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use JsonException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Writes the order file with the introduction first and duplicates removed, then flushes the chapter cache.
 */
class WriteChapterOrder implements StoresHandbook
{
    use AsAction;

    public function __construct(
        #[Storage(self::DISK)] protected Filesystem $disk,
    ) {}

    /**
     * @param  array<int, string>  $slugs
     *
     * @throws JsonException when the slugs cannot be encoded
     */
    public function handle(array $slugs): void
    {
        $rest = array_values(array_unique(array_filter(
            $slugs,
            fn (string $slug): bool => $slug !== self::INTRODUCTION,
        )));

        $this->disk->put(
            self::ORDER_FILE,
            json_encode([self::INTRODUCTION, ...$rest], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );

        Cache::tags([self::CACHE_TAG])->flush();
    }
}
