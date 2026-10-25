<?php

namespace App\Support\MediaLibrary;

use App\Contracts\HasBlizzardIcons;
use App\Contracts\HasCharacterMedia;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\FileRemover\DefaultFileRemover;
use Spatie\MediaLibrary\Support\FileRemover\FileRemover;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

/**
 * Removes a media item's files. Media stored in a shared Blizzard directory lose only
 * their own exact files, skipping any path another media row still resolves to; all
 * other media are removed by Media Library's default remover.
 */
final class SharedPathFileRemover implements FileRemover
{
    private DefaultFileRemover $default;

    public function __construct(protected Filesystem $mediaFileSystem, protected Factory $filesystem)
    {
        $this->default = new DefaultFileRemover($mediaFileSystem, $filesystem);
    }

    public function removeAllFiles(Media $media): void
    {
        if (! $this->usesSharedDirectory($media)) {
            $this->default->removeAllFiles($media);

            return;
        }

        $this->deleteUnshared($media, $this->filePaths($media));
    }

    public function removeResponsiveImages(Media $media, string $conversionName): void
    {
        if (! $this->usesSharedDirectory($media)) {
            $this->default->removeResponsiveImages($media, $conversionName);

            return;
        }

        $this->deleteUnshared($media, $this->responsiveImagePaths($media, [$conversionName]));
    }

    public function removeFile(string $path, string $disk): void
    {
        $this->default->removeFile($path, $disk);
    }

    /**
     * Whether the media is stored in one of the shared Blizzard icon or portrait directories.
     */
    private function usesSharedDirectory(Media $media): bool
    {
        $pathGenerator = PathGeneratorFactory::create($media);

        return ($pathGenerator instanceof BlizzardIconPathGenerator && $media->collection_name === HasBlizzardIcons::MEDIA_COLLECTION)
            || ($pathGenerator instanceof CharacterMediaPathGenerator && $media->collection_name === HasCharacterMedia::MEDIA_COLLECTION);
    }

    /**
     * Delete each of the given files that no other media row resolves to.
     *
     * @param  Collection<int, array{disk: string, path: string}>  $files
     */
    private function deleteUnshared(Media $media, Collection $files): void
    {
        $filesInUse = $this->filesInUseByOtherMedia($media)
            ->map(fn (array $file): string => $this->fileKey($file))
            ->all();

        $files
            ->reject(fn (array $file): bool => in_array($this->fileKey($file), $filesInUse, true))
            ->groupBy('disk')
            ->each(function (Collection $filesOnDisk, string $disk): void {
                $this->filesystem->disk($disk)->delete($filesOnDisk->pluck('path')->all());
            });
    }

    /**
     * The files of every other media row in the same collection and disk with the same file name.
     *
     * @return Collection<int, array{disk: string, path: string}>
     */
    private function filesInUseByOtherMedia(Media $media): Collection
    {
        return $media->newQuery()
            ->whereKeyNot($media->getKey())
            ->where('disk', $media->disk)
            ->where('collection_name', $media->collection_name)
            ->where('file_name', $media->file_name)
            ->get()
            ->flatMap(fn (Media $otherMedia): Collection => $this->filePaths($otherMedia));
    }

    /**
     * The original, conversion and responsive-image files of the media.
     *
     * @return Collection<int, array{disk: string, path: string}>
     */
    private function filePaths(Media $media): Collection
    {
        $conversionsDisk = $media->conversions_disk ?: $media->disk;

        $conversionPaths = collect($media->getMediaConversionNames())
            ->map(fn (string $conversionName): array => [
                'disk' => $conversionsDisk,
                'path' => $media->getPathRelativeToRoot($conversionName),
            ]);

        return collect([['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()]])
            ->merge($conversionPaths)
            ->merge($this->responsiveImagePaths($media, array_keys($media->responsive_images ?? [])));
    }

    /**
     * The responsive-image files generated for the given conversions of the media.
     *
     * @param  array<int, string>  $conversionNames
     * @return Collection<int, array{disk: string, path: string}>
     */
    private function responsiveImagePaths(Media $media, array $conversionNames): Collection
    {
        $conversionsDisk = $media->conversions_disk ?: $media->disk;
        $directory = PathGeneratorFactory::create($media)->getPathForResponsiveImages($media);

        return collect($conversionNames)
            ->flatMap(fn (string $conversionName): array => $media->responsiveImages($conversionName)->getFilenames())
            ->map(fn (string $fileName): array => ['disk' => $conversionsDisk, 'path' => $directory.$fileName])
            ->values();
    }

    /**
     * A string identifying a file by disk and path.
     *
     * @param  array{disk: string, path: string}  $file
     */
    private function fileKey(array $file): string
    {
        return $file['disk'].':'.$file['path'];
    }
}
