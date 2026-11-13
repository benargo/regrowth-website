<?php

namespace App\Rules\GuildRosterManager;

use App\Models\GameVersion;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates that a slug names a current roster whose WTF folder the desktop app can find.
 */
class SyncableGameVersion implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isSyncable = GameVersion::currentRosters()->contains(
            fn (GameVersion $gameVersion): bool => $gameVersion->slug === $value && $gameVersion->wow_client_folder !== null,
        );

        if (! $isSyncable) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
