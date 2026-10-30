<?php

namespace App\Actions\RaidInformation;

use App\Contracts\Actions\RaidInformation\StoresRaidInformation;
use App\Jobs\RaidInformation\ResolveChapterChannels;
use App\Rules\ChapterContent;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use League\Flysystem\UnableToReadFile;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Validates and writes a chapter file, returning whether the stored content changed.
 *
 * Flushes the chapter cache and dispatches channel resolution for mentioned channels after a write.
 * The caller holds the write lock.
 */
class PutChapter implements StoresRaidInformation
{
    use AsAction;

    public function __construct(
        #[Storage(self::DISK)] protected Filesystem $disk,
    ) {}

    /**
     * @throws InvalidArgumentException When the slug does not match the slug pattern.
     * @throws ValidationException When the content fails validation.
     */
    public function handle(string $slug, string $content): bool
    {
        if (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            throw new InvalidArgumentException("Invalid chapter slug [{$slug}].");
        }

        Validator::make(
            ['content' => $content],
            ['content' => ['required', 'string', ChapterContent::forSlug($slug)]],
        )->validate();

        $path = "{$slug}.md";

        try {
            $stored = $this->disk->get($path);
        } catch (UnableToReadFile) {
            $stored = null;
        }

        if ($stored === $content) {
            return false;
        }

        $this->disk->put($path, $content);

        Cache::tags([self::CACHE_TAG])->flush();

        preg_match_all('/<#(\d+)>/', $content, $matches);
        $channelIds = array_values(array_unique($matches[1]));

        if ($channelIds !== []) {
            ResolveChapterChannels::dispatch($channelIds);
        }

        return true;
    }
}
