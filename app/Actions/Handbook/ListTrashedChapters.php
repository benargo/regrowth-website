<?php

namespace App\Actions\Handbook;

use App\Contracts\Actions\Handbook\StoresHandbook;
use App\Data\Handbook\TrashedChapterData;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Lists the trashed chapters whose name is a valid slug, newest first, with the date each is permanently deleted.
 */
class ListTrashedChapters implements StoresHandbook
{
    use AsAction;

    public function __construct(
        #[Storage(self::DISK)] protected Filesystem $disk,
    ) {}

    /**
     * @return Collection<int, TrashedChapterData>
     */
    public function handle(): Collection
    {
        return collect($this->disk->files(self::TRASH_DIRECTORY))
            ->filter(fn (string $path): bool => str_ends_with($path, '.md') && preg_match(self::SLUG_PATTERN, basename($path, '.md')) === 1)
            ->map(function (string $path): TrashedChapterData {
                $heading = ParseChapterHeading::run((string) $this->disk->get($path));
                $trashedAt = CarbonImmutable::createFromTimestamp($this->disk->lastModified($path));
                $purgesAt = $trashedAt->addDays(self::TRASH_DAYS);

                return new TrashedChapterData(
                    slug: basename($path, '.md'),
                    title: $heading->title,
                    emoji: $heading->emoji,
                    trashedAt: $trashedAt,
                    purgesAt: $purgesAt,
                    daysRemaining: min(self::TRASH_DAYS, max(0, (int) ceil(now()->diffInDays($purgesAt, false)))),
                );
            })
            ->sortByDesc(fn (TrashedChapterData $chapter): int => $chapter->trashedAt->getTimestamp())
            ->values();
    }
}
