<?php

namespace App\Actions\RaidInformation;

use App\Data\RaidInformation\ChapterData;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Finds a single chapter by slug, or null when none exists.
 */
class FindChapter
{
    use AsAction;

    public function handle(string $slug): ?ChapterData
    {
        return ListChapters::run()->first(fn (ChapterData $chapter): bool => $chapter->slug === $slug);
    }
}
