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
        $entries = $candidates->map(fn (Character $character): array => $this->entry($character))->values();

        if (! $usesSurnames) {
            return $this->matchTiers(Character::splitName($name, true)['name'], $entries, 'fullName', 'foldedFullName');
        }

        $match = $this->matchTiers($name, $entries, 'fullName', 'foldedFullName');

        if ($match !== null || preg_match('/\s/u', $name) === 1) {
            return $match;
        }

        return $this->matchTiers($name, $entries, 'firstName', 'foldedFirstName');
    }

    /**
     * The candidate with its trimmed full name and first name, and the folded
     * form of each.
     *
     * @return array{character: Character, fullName: string, foldedFullName: string, firstName: string, foldedFirstName: string}
     */
    private function entry(Character $character): array
    {
        $fullName = trim($character->full_name);
        $firstName = trim($character->name);

        return [
            'character' => $character,
            'fullName' => $fullName,
            'foldedFullName' => $this->fold($fullName),
            'firstName' => $firstName,
            'foldedFirstName' => $this->fold($firstName),
        ];
    }

    /**
     * Match the input against each entry's name under the exact key, then its
     * folded form under the folded key with case and accents ignored.
     *
     * @param  Collection<int, array{character: Character, fullName: string, foldedFullName: string, firstName: string, foldedFirstName: string}>  $entries
     *
     * @throws MultipleCharactersFoundException
     */
    private function matchTiers(string $input, Collection $entries, string $exactKey, string $foldedKey): ?Character
    {
        $exactMatches = $entries
            ->filter(fn (array $entry): bool => $entry[$exactKey] === $input)
            ->pluck('character')
            ->values();

        if ($exactMatches->count() > 1) {
            throw new MultipleCharactersFoundException($exactMatches);
        }

        if ($exactMatches->count() === 1) {
            return $exactMatches->first();
        }

        $foldedInput = $this->fold($input);

        $foldedMatches = $entries
            ->filter(fn (array $entry): bool => $entry[$foldedKey] === $foldedInput)
            ->pluck('character')
            ->values();

        if ($foldedMatches->count() > 1) {
            throw new MultipleCharactersFoundException($foldedMatches);
        }

        return $foldedMatches->first();
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
