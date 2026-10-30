<?php

namespace App\Actions\Handbook;

use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Counts the content length in UTF-16 code units, the unit Discord's message limit uses.
 */
class CountDiscordCharacters
{
    use AsAction;

    public function handle(string $content): int
    {
        return intdiv(strlen(mb_convert_encoding($content, 'UTF-16LE', 'UTF-8')), 2);
    }
}
