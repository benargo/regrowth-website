<?php

namespace App\Http\Integrations\Blizzard\Support;

use Illuminate\Support\Str;

/**
 * Builds the name segment Blizzard's profile API expects for characters and guilds.
 *
 * Blizzard lowercases the name, keeps its diacritics and hyphenates spaces
 * (`Thräll Runetotem` → `thräll-runetotem`). Str::slug() must not be used here: it
 * transliterates to ASCII, so `Ízepo` would resolve a different character, `Izepo`.
 */
final class NameSlug
{
    public static function from(string $name): string
    {
        return Str::of($name)->trim()->lower()->replaceMatches('/\s+/u', '-')->toString();
    }
}
