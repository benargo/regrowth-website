<?php

namespace App\Actions\RaidInformation;

use App\Contracts\Actions\RaidInformation\StoresRaidInformation;
use App\Rules\MatchesChapterHash;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Saves a live chapter when its stored content still matches the hash the editor was opened with.
 */
class UpdateChapter implements StoresRaidInformation
{
    use AsAction;

    public function __construct(
        #[Storage(self::DISK)] protected Filesystem $disk,
    ) {}

    /**
     * @throws ValidationException When the chapter is not live, the stored content has changed or the new content is invalid.
     */
    public function handle(string $slug, string $content, string $baseHash): bool
    {
        return Cache::lock(self::WRITE_LOCK, 10)->block(5, function () use ($slug, $content, $baseHash): bool {
            Validator::make(
                ['base_hash' => $baseHash],
                ['base_hash' => [new MatchesChapterHash($this->disk, $slug)]],
            )->validate();

            return PutChapter::run($slug, $content);
        });
    }
}
