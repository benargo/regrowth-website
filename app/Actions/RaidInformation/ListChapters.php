<?php

namespace App\Actions\RaidInformation;

use App\Contracts\Actions\RaidInformation\StoresRaidInformation;
use App\Data\RaidInformation\ChapterData;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Lists every chapter file whose name is a valid slug in display order, cached for the configured TTL.
 */
class ListChapters implements StoresRaidInformation
{
    use AsAction;

    public function __construct(
        #[Storage(self::DISK)] protected Filesystem $disk,
    ) {}

    /**
     * @return Collection<int, ChapterData>
     */
    public function handle(): Collection
    {
        $files = Cache::tags([self::CACHE_TAG])->remember(
            'raid-information:chapters',
            now()->addMinutes(self::TTL_MINUTES),
            fn (): array => $this->load(),
        );

        return collect($files)->map(function (array $file): ChapterData {
            $heading = ParseChapterHeading::run($file['content']);

            return new ChapterData(
                slug: $file['slug'],
                title: $heading->title,
                emoji: $heading->emoji,
                content: $file['content'],
                characterCount: CountDiscordCharacters::run($file['content']),
                hash: sha1($file['content']),
                updatedAt: CarbonImmutable::createFromTimestamp($file['updated_at']),
                isIntroduction: $file['slug'] === self::INTRODUCTION,
            );
        })->values();
    }

    /**
     * @return array<int, array{slug: string, content: string, updated_at: int}>
     */
    private function load(): array
    {
        $files = collect($this->disk->files())
            ->filter(fn (string $path): bool => str_ends_with($path, '.md') && preg_match(self::SLUG_PATTERN, basename($path, '.md')) === 1)
            ->mapWithKeys(function (string $path): array {
                $slug = basename($path, '.md');

                return [$slug => [
                    'slug' => $slug,
                    'content' => (string) $this->disk->get($path),
                    'updated_at' => $this->disk->lastModified($path),
                ]];
            });

        $listed = collect([self::INTRODUCTION, ...ReadChapterOrder::run()])
            ->unique()
            ->filter(fn (string $slug): bool => $files->has($slug));

        $unlisted = $files->pluck('slug')
            ->diff($listed)
            ->sort()
            ->values();

        return $listed->concat($unlisted)
            ->map(fn (string $slug): array => $files->get($slug))
            ->values()
            ->all();
    }
}
