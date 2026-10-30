<?php

namespace App\Actions\Handbook;

use App\Contracts\Actions\Handbook\StoresHandbook;
use Closure;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Copies a chapter into the trash as a new file, deletes the live file and removes the chapter from the order.
 */
class TrashChapter implements StoresHandbook
{
    use AsAction;

    public function __construct(
        #[Storage(self::DISK)] protected Filesystem $disk,
    ) {}

    /**
     * @throws ValidationException When the slug is the introduction.
     */
    public function handle(string $slug): void
    {
        Validator::make(['chapter' => $slug], [
            'chapter' => [function (string $attribute, string $value, Closure $fail): void {
                if ($value === self::INTRODUCTION) {
                    $fail('The introduction can’t be deleted.');
                }
            }],
        ])->validate();

        Cache::lock(self::WRITE_LOCK, 10)->block(5, function () use ($slug): void {
            $this->disk->put(self::TRASH_DIRECTORY."/{$slug}.md", (string) $this->disk->get("{$slug}.md"));
            $this->disk->delete("{$slug}.md");

            WriteChapterOrder::run(array_values(array_diff(ReadChapterOrder::run(), [$slug])));
        });
    }
}
