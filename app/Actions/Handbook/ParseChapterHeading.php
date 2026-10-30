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

    /**
     * Matches any Extended_Pictographic or Regional_Indicator code point, as of Unicode 15.
     */
    private const string EMOJI_PATTERN = '/[\x{A9}\x{AE}\x{203C}\x{2049}\x{2122}\x{2139}\x{2194}-\x{2199}\x{21A9}-\x{21AA}'
        .'\x{231A}-\x{231B}\x{2328}\x{2388}\x{23CF}\x{23E9}-\x{23F3}\x{23F8}-\x{23FA}\x{24C2}\x{25AA}-\x{25AB}\x{25B6}'
        .'\x{25C0}\x{25FB}-\x{25FE}\x{2600}-\x{2605}\x{2607}-\x{2612}\x{2614}-\x{2685}\x{2690}-\x{2705}\x{2708}-\x{2712}'
        .'\x{2714}\x{2716}\x{271D}\x{2721}\x{2728}\x{2733}-\x{2734}\x{2744}\x{2747}\x{274C}\x{274E}\x{2753}-\x{2755}'
        .'\x{2757}\x{2763}-\x{2767}\x{2795}-\x{2797}\x{27A1}\x{27B0}\x{27BF}\x{2934}-\x{2935}\x{2B05}-\x{2B07}'
        .'\x{2B1B}-\x{2B1C}\x{2B50}\x{2B55}\x{3030}\x{303D}\x{3297}\x{3299}\x{1F000}-\x{1F0FF}\x{1F10D}-\x{1F10F}'
        .'\x{1F12F}\x{1F16C}-\x{1F171}\x{1F17E}-\x{1F17F}\x{1F18E}\x{1F191}-\x{1F19A}\x{1F1AD}-\x{1F1FF}'
        .'\x{1F201}-\x{1F20F}\x{1F21A}\x{1F22F}\x{1F232}-\x{1F23A}\x{1F23C}-\x{1F23F}\x{1F249}-\x{1F3FA}'
        .'\x{1F400}-\x{1F53D}\x{1F546}-\x{1F64F}\x{1F680}-\x{1F6FF}\x{1F774}-\x{1F77F}\x{1F7D5}-\x{1F7FF}'
        .'\x{1F80C}-\x{1F80F}\x{1F848}-\x{1F84F}\x{1F85A}-\x{1F85F}\x{1F888}-\x{1F88F}\x{1F8AE}-\x{1F8FF}'
        .'\x{1F90C}-\x{1F93A}\x{1F93C}-\x{1F945}\x{1F947}-\x{1FAFF}\x{1FC00}-\x{1FFFD}]/u';

    public function handle(string $content): ChapterHeadingData
    {
        $firstLine = str($content)->split('/\R/u', 2)->first(default: '');
        $heading = str($firstLine)->replaceMatches('/^#+\s*/u', '')->trim()->toString();

        if (preg_match('/^(<a?:\w+:\d+>)\s*(.*)$/su', $heading, $matches) === 1) {
            return new ChapterHeadingData(trim($matches[2]), $matches[1]);
        }

        if (preg_match('/^(\X)(.*)$/su', $heading, $matches) === 1
            && preg_match(self::EMOJI_PATTERN, $matches[1]) === 1) {
            return new ChapterHeadingData(trim($matches[2]), $matches[1]);
        }

        return new ChapterHeadingData($heading, null);
    }
}
