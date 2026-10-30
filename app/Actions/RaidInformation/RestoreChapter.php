<?php

namespace App\Actions\RaidInformation;

use App\Contracts\Actions\RaidInformation\StoresRaidInformation;
use Closure;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Copies a trashed chapter back as a new live file, deletes the trashed copy and appends the chapter to the order.
 */
class RestoreChapter implements StoresRaidInformation
{
    use AsAction;

    public function __construct(
        #[Storage(self::DISK)] protected Filesystem $disk,
    ) {}

    /**
     * @throws ValidationException When a live chapter already uses the slug.
     */
    public function handle(string $slug): void
    {
        Cache::lock(self::WRITE_LOCK, 10)->block(5, function () use ($slug): void {
            Validator::make(['chapter' => $slug], [
                'chapter' => [function (string $attribute, string $value, Closure $fail): void {
                    if ($this->disk->exists("{$value}.md")) {
                        $fail("A live chapter already uses “{$value}”. Delete or rename it first.");
                    }
                }],
            ])->validate();

            $trashed = self::TRASH_DIRECTORY."/{$slug}.md";

            $this->disk->put("{$slug}.md", (string) $this->disk->get($trashed));
            $this->disk->delete($trashed);

            WriteChapterOrder::run([...ReadChapterOrder::run(), $slug]);
        });
    }
}
