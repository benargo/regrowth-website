<?php

namespace App\Console\Commands;

use App\Contracts\HasBlizzardIcons;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use App\Jobs\Discord\RefreshUserAvatars;
use App\Models\User;
use App\Support\MediaLibrary\UnknownBlizzardIcon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;
use Throwable;

#[Signature('app:migrate-public-storage-to-s3 {--dry-run : Report what would move without writing}')]
#[Description('Copy media rows and loose files from the public disk to the s3 disk, ensure the questionmark icon exists and backfill user avatars.')]
class MigratePublicStorageToS3 extends Command implements HasBlizzardIcons
{
    private const string SOURCE_DISK = 'public';

    private const string TARGET_DISK = 's3';

    /**
     * Top-level directories on the source disk whose files are copied as loose files.
     */
    private const array LOOSE_DIRECTORIES = ['blizzard-cdn', 'images'];

    private int $rowsMoved = 0;

    private int $rowsFailed = 0;

    private int $filesCopied = 0;

    private int $filesFailed = 0;

    public function __construct(
        private readonly FilesystemManager $filesystem,
        #[Config('media-library.remote.extra_headers.CacheControl')]
        private readonly ?string $mediaCacheControl = null,
    ) {
        parent::__construct();
    }

    public function handle(
        RenderConnector $render,
        UnknownBlizzardIcon $unknownIcon,
        #[Config('media-library.disk_name')]
        string $mediaDisk,
    ): int {
        if (! $this->isDryRun() && $mediaDisk !== self::TARGET_DISK) {
            $this->error('media-library.disk_name is "'.$mediaDisk.'"; set MEDIA_DISK='.self::TARGET_DISK.' before migrating.');

            return self::FAILURE;
        }

        $source = $this->filesystem->disk(self::SOURCE_DISK);
        $target = $this->filesystem->disk(self::TARGET_DISK);

        $this->rowsMoved = $this->rowsFailed = $this->filesCopied = $this->filesFailed = 0;

        $this->migrateMediaRows($source, $target);
        $this->copyLooseFiles($source, $target);
        $this->ensureQuestionmark($target, $render, $unknownIcon);
        $this->dispatchAvatarBackfill();
        $this->printSummary();

        return $this->rowsFailed > 0 || $this->filesFailed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Copy every file of each `public` media row to `s3`, then repoint the row once all of its files are present.
     */
    private function migrateMediaRows(Filesystem $source, Filesystem $target): void
    {
        foreach (Media::where('disk', self::SOURCE_DISK)->lazyById() as $media) {
            try {
                $pairs = $this->filePairs($media, $source);

                $missing = collect($pairs)->first(fn (array $pair): bool => ! $source->exists($pair['source']));

                if ($missing !== null) {
                    throw new RuntimeException("source file {$missing['source']} is missing on ".self::SOURCE_DISK.'.');
                }

                if ($this->isDryRun()) {
                    $this->rowsMoved++;

                    continue;
                }

                foreach ($pairs as $pair) {
                    $this->writeMediaFile($source, $target, $pair['source'], $pair['target']);
                }

                Media::whereKey($media->id)->update(['disk' => self::TARGET_DISK, 'conversions_disk' => self::TARGET_DISK]);

                $this->rowsMoved++;
            } catch (Throwable $exception) {
                $this->rowsFailed++;
                $this->error("Media {$media->id} failed: {$exception->getMessage()}");
            }
        }
    }

    /**
     * The source and target paths of a media row's original, generated conversions and responsive images.
     *
     * @return list<array{source: string, target: string}>
     */
    private function filePairs(Media $media, Filesystem $source): array
    {
        $original = $media->getPathRelativeToRoot();

        $pairs = [[
            'source' => $source->exists($original) ? $original : $original.$media->file_name,
            'target' => $original,
        ]];

        foreach ($media->getGeneratedConversions()->filter()->keys() as $conversion) {
            $path = $media->getPathRelativeToRoot($conversion);
            $pairs[] = ['source' => $path, 'target' => $path];
        }

        $responsiveDirectory = PathGeneratorFactory::create($media)->getPathForResponsiveImages($media);

        foreach (array_keys($media->responsive_images ?? []) as $conversion) {
            foreach ($media->responsiveImages($conversion)->getFilenames() as $fileName) {
                $path = $responsiveDirectory.$fileName;
                $pairs[] = ['source' => $path, 'target' => $path];
            }
        }

        return $pairs;
    }

    /**
     * Write one media file to the target disk with the Media Library cache header, unless it is already there.
     */
    private function writeMediaFile(Filesystem $source, Filesystem $target, string $sourcePath, string $targetPath): void
    {
        if ($target->exists($targetPath)) {
            return;
        }

        $written = $this->copyStream($source, $target, $sourcePath, $targetPath, ['CacheControl' => $this->mediaCacheControl]);

        if (! $written) {
            throw new RuntimeException("writing {$targetPath} to ".self::TARGET_DISK.' failed.');
        }
    }

    /**
     * Copy each loose mirror and image file missing on the target disk, skipping legacy doubled file names.
     */
    private function copyLooseFiles(Filesystem $source, Filesystem $target): void
    {
        $existing = collect(self::LOOSE_DIRECTORIES)
            ->flatMap(fn (string $directory): array => $target->allFiles($directory))
            ->flip();

        $paths = collect(self::LOOSE_DIRECTORIES)
            ->flatMap(fn (string $directory): array => $source->allFiles($directory))
            ->reject(fn (string $path): bool => $this->isDoubledFileName(basename($path)))
            ->reject(fn (string $path): bool => $existing->has($path));

        foreach ($paths as $path) {
            if ($this->isDryRun()) {
                $this->filesCopied++;

                continue;
            }

            if ($this->copyStream($source, $target, $path, $path)) {
                $this->filesCopied++;
            } else {
                $this->filesFailed++;
                $this->error("Copying {$path} failed.");
            }
        }
    }

    /**
     * Store the questionmark icon on the target disk from the render CDN, or from the bundled copy when the fetch fails.
     */
    private function ensureQuestionmark(Filesystem $target, RenderConnector $render, UnknownBlizzardIcon $unknownIcon): void
    {
        if ($this->isDryRun() || $target->exists($unknownIcon->path())) {
            return;
        }

        try {
            $render->send(new FetchIconRequest(self::BLIZZARD_UNKNOWN_ICON));
        } catch (Throwable $exception) {
            $this->warn("Fetching the questionmark icon failed: {$exception->getMessage()}");
        }

        if ($target->exists($unknownIcon->path())) {
            return;
        }

        $written = $target->put($unknownIcon->path(), file_get_contents(resource_path('images/'.self::BLIZZARD_UNKNOWN_ICON.'.'.self::DEFAULT_MEDIA_FILE_EXTENSION)));

        if (! $written) {
            $this->filesFailed++;
            $this->error("Writing {$unknownIcon->path()} failed.");
        }
    }

    /**
     * Queue the avatar backfill for every user.
     */
    private function dispatchAvatarBackfill(): void
    {
        if ($this->isDryRun()) {
            return;
        }

        RefreshUserAvatars::dispatch();
    }

    /**
     * Print the row and file counts, or the would-be counts and the avatar hash count on a dry run.
     */
    private function printSummary(): void
    {
        if (! $this->isDryRun()) {
            $this->table(['Metric', 'Count'], [
                ['Rows moved', $this->rowsMoved],
                ['Rows failed', $this->rowsFailed],
                ['Files copied', $this->filesCopied],
                ['Files failed', $this->filesFailed],
            ]);

            return;
        }

        $this->table(['Metric', 'Count'], [
            ['Rows that would move', $this->rowsMoved],
            ['Rows that would fail', $this->rowsFailed],
            ['Files that would be copied', $this->filesCopied],
            ['Files that would fail', $this->filesFailed],
            ['Users with avatar hashes', User::whereNotNull('avatar')->orWhereNotNull('guild_avatar')->count()],
        ]);
    }

    /**
     * Stream a file from the source disk to the target disk; false when the read or the write fails.
     *
     * @param  array<string, mixed>  $options
     */
    private function copyStream(Filesystem $source, Filesystem $target, string $sourcePath, string $targetPath, array $options = []): bool
    {
        $stream = $source->readStream($sourcePath);

        if (! is_resource($stream)) {
            return false;
        }

        try {
            return $target->writeStream($targetPath, $stream, $options) !== false;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Whether a file name is one name repeated twice, such as `x.jpgx.jpg`.
     */
    private function isDoubledFileName(string $fileName): bool
    {
        $length = strlen($fileName);

        return $length > 0
            && $length % 2 === 0
            && substr($fileName, 0, $length / 2) === substr($fileName, $length / 2);
    }

    private function isDryRun(): bool
    {
        return (bool) $this->option('dry-run');
    }
}
