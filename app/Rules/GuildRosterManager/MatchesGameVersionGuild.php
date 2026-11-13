<?php

namespace App\Rules\GuildRosterManager;

use App\Models\GameVersion;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates that a GRM guild key matches the guild of the current roster named by the data's `gameVersion` slug.
 */
class MatchesGameVersionGuild implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Set the data under validation.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $gameVersion = GameVersion::currentRosters()->firstWhere('slug', $this->data['gameVersion'] ?? null);

        if ($value !== $gameVersion?->grm_guild_key) {
            $fail("The {$attribute} does not match this game version's guild.");
        }
    }
}
