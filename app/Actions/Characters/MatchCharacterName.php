<?php

namespace App\Actions\Characters;

use App\Exceptions\MultipleCharactersFoundException;
use App\Models\Character;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use Normalizer;

/**
 * Finds the character a name from outside the app (Warcraft Logs or
 * Raid-Helper) refers to, among candidates the caller has already loaded.
 * The candidates must all belong to one game version, and the caller says
 * whether that version uses surnames.
 *
 * Names on both sides are trimmed before any comparison. Surnames are
 * all-or-nothing per game version: without them the input's surname is
 * dropped first, so "Thrall Stormrage" is matched as "Thrall"; with them the
 * input must be the whole stored name.
 *
 * Then an exact match wins (names are unique within a version, so there is at
 * most one). Failing that, the names are compared with case and accents
 * ignored. Several matches there are ambiguous and throw, because guessing
 * would credit the wrong character; what to do about it is the caller's
 * decision, so this never logs.
 *
 * Matching runs on the given collection rather than in SQL because the accent
 * fold can't be done against the binary collation of characters.name.
 */
class MatchCharacterName
{
    use AsAction;

    /**
     * @param  Collection<int, Character>  $candidates
     *
     * @throws MultipleCharactersFoundException
     */
    public function handle(string $name, Collection $candidates, bool $usesSurnames = true): ?Character
    {
        $name = trim($name);
        $input = $usesSurnames ? $name : $this->withoutSurname($name);

        $exactMatch = $candidates->first(fn (Character $character): bool => trim($character->name) === $input);

        if ($exactMatch !== null) {
            return $exactMatch;
        }

        $foldedInput = $this->fold($input);

        $foldedMatches = $candidates
            ->filter(fn (Character $character): bool => $this->fold(trim($character->name)) === $foldedInput)
            ->values();

        if ($foldedMatches->count() > 1) {
            throw new MultipleCharactersFoundException($foldedMatches);
        }

        return $foldedMatches->first();
    }

    /**
     * The first word of the name. Invalid UTF-8 is returned whole rather than
     * split, since the pattern can't run on it.
     */
    private function withoutSurname(string $name): string
    {
        return str($name)->split('/\s+/u', 2)->first(default: $name);
    }

    /**
     * Decompose (NFKD), strip combining marks and lowercase. Invalid UTF-8
     * can't be decomposed, so it is only lowercased.
     */
    private function fold(string $name): string
    {
        $decomposed = Normalizer::normalize($name, Normalizer::FORM_KD);

        if ($decomposed === false) {
            return str($name)->lower()->toString();
        }

        return str($decomposed)->replaceMatches('/\p{Mn}/u', '')->lower()->toString();
    }
}
