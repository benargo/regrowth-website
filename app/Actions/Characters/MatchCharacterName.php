<?php

namespace App\Actions\Characters;

use App\Exceptions\MultipleCharactersFoundException;
use App\Models\Character;
use Closure;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use Normalizer;

/**
 * Finds the character a name from outside the app (Warcraft Logs or
 * Raid-Helper) refers to, among candidates the caller has already loaded.
 * The candidates must all belong to one game version, and the caller says
 * whether that version uses surnames.
 *
 * Names on both sides are trimmed before any comparison. Without surnames
 * the input's surname is dropped first, so "Thrall Stormrage" is matched as
 * "Thrall", against each character's full name. With surnames the input is
 * matched against each character's full name ("Thrall Stormrage"); when that
 * finds nothing and the input is a single word, it is matched against the
 * first names alone.
 *
 * Each comparison has two tiers. An exact match wins; several exact matches
 * are ambiguous and throw. Failing that, the names are compared with case and
 * accents ignored, and several matches there throw too. Guessing would credit
 * the wrong character; what to do about it is the caller's decision, so this
 * never logs.
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
        $fullName = fn (Character $character): string => trim($character->full_name);

        if (! $usesSurnames) {
            return $this->matchTiers($this->withoutSurname($name), $candidates, $fullName);
        }

        $match = $this->matchTiers($name, $candidates, $fullName);

        if ($match !== null || preg_match('/\s/u', $name) === 1) {
            return $match;
        }

        return $this->matchTiers($name, $candidates, fn (Character $character): string => trim($character->name));
    }

    /**
     * Match the input against the names the closure reads, exact first and
     * then with case and accents ignored.
     *
     * @param  Collection<int, Character>  $candidates
     * @param  Closure(Character): string  $nameOf
     *
     * @throws MultipleCharactersFoundException
     */
    private function matchTiers(string $input, Collection $candidates, Closure $nameOf): ?Character
    {
        $exactMatches = $candidates
            ->filter(fn (Character $character): bool => $nameOf($character) === $input)
            ->values();

        if ($exactMatches->count() > 1) {
            throw new MultipleCharactersFoundException($exactMatches);
        }

        if ($exactMatches->count() === 1) {
            return $exactMatches->first();
        }

        $foldedInput = $this->fold($input);

        $foldedMatches = $candidates
            ->filter(fn (Character $character): bool => $this->fold($nameOf($character)) === $foldedInput)
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
