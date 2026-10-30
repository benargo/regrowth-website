<?php

namespace App\Actions\Handbook;

use App\Contracts\Actions\Handbook\StoresHandbook;
use App\Data\Handbook\ChapterData;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Rewrites the order file so the given slugs follow the introduction.
 */
class ReorderChapters implements StoresHandbook
{
    use AsAction;

    /**
     * @param  array<int, string>  $slugs
     *
     * @throws ValidationException
     */
    public function handle(array $slugs): void
    {
        Cache::lock(self::WRITE_LOCK, 10)->block(5, function () use ($slugs): void {
            $live = ListChapters::run()
                ->reject(fn (ChapterData $chapter): bool => $chapter->isIntroduction)
                ->pluck('slug')
                ->all();

            Validator::make(['slugs' => $slugs], [
                'slugs' => [function (string $attribute, array $value, Closure $fail) use ($live): void {
                    if (count($value) !== count($live) || array_diff($value, $live) !== [] || array_diff($live, $value) !== []) {
                        $fail('The chapter list has changed; reload the page and try again.');
                    }
                }],
            ])->validate();

            WriteChapterOrder::run([self::INTRODUCTION, ...$slugs]);
        });
    }
}
