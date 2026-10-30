<?php

namespace App\Actions\Handbook;

use App\Contracts\Actions\Handbook\StoresHandbook;
use App\Data\Handbook\ChapterHeadingData;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reads the title and optional leading emoji from the first line of chapter content.
 */
class ParseChapterHeading implements StoresHandbook
{
    use AsAction;

    public function handle(string $content): ChapterHeadingData
    {
        $firstLine = str($content)->split('/\R/u', 2)->first(default: '');
        $heading = str($firstLine)->replaceMatches('/^#+\s*/u', '')->trim()->toString();

        if (preg_match('/^(<a?:\w+:\d+>)\s*(.*)$/su', $heading, $matches) === 1) {
            return new ChapterHeadingData(trim($matches[2]), $matches[1]);
        }

        if (preg_match('/^(\X)(.*)$/su', $heading, $matches) === 1
            && preg_match('/\p{Extended_Pictographic}|\p{Regional_Indicator}/u', $matches[1]) === 1) {
            return new ChapterHeadingData(trim($matches[2]), $matches[1]);
        }

        return new ChapterHeadingData($heading, null);
    }
}
