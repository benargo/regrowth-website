<?php

namespace App\Http\Requests\Raid;

use App\Actions\Attendance\FindReportDateRange;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Models\Character;
use App\Models\GameVersion;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the attendance filters and exposes their values, scoped to the
 * user's current game version.
 */
class AttendanceMatrixRequest extends FormRequest
{
    private const string ID_LIST_RULE = 'regex:/^(all|none|\d+(,\d+)*)$/';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $today = today()->toDateString();
        $dateRules = ['nullable', 'date', "before_or_equal:{$today}"];

        if ($this->earliestDate() !== null) {
            $dateRules[] = "after_or_equal:{$this->earliestDate()}";
        }

        return [
            'character' => ['nullable', 'integer', 'exists:characters,id'],
            'rank_ids' => ['nullable', 'string', self::ID_LIST_RULE],
            'zone_ids' => ['nullable', 'string', self::ID_LIST_RULE],
            'guild_tag_ids' => ['nullable', 'string', self::ID_LIST_RULE],
            'since_date' => [...$dateRules, ...($this->filled('before_date') ? ['before_or_equal:before_date'] : [])],
            'before_date' => [...$dateRules, ...($this->filled('since_date') ? ['after_or_equal:since_date'] : [])],
            'combine_linked_characters' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The user's current game version.
     */
    public function gameVersion(): ?GameVersion
    {
        return ResolveCurrentGameVersion::run();
    }

    /**
     * Whether the current game version has counted attendance; false without a version.
     */
    public function hasCountedAttendance(): bool
    {
        return once(fn (): bool => $this->gameVersion()?->hasCountedAttendance() ?? false);
    }

    /**
     * @return array<int, int>|null The version's ranks among those requested; null for the default.
     */
    public function rankIds(): ?array
    {
        return once(fn (): ?array => $this->idFilter('rank_ids', fn (array $ids): array => $this->gameVersion()
            ->guildRanks()->whereKey($ids)->orderBy('id')->pluck('id')->all()));
    }

    /**
     * @return array<int, int>|null The requested zones used by the version's reports; null for every zone.
     */
    public function zoneIds(): ?array
    {
        return once(fn (): ?array => $this->idFilter('zone_ids', fn (array $ids): array => $this->gameVersion()
            ->reports()->whereIn('zone_id', $ids)->distinct()->orderBy('zone_id')->pluck('zone_id')->all()));
    }

    /**
     * @return array<int, int>|null The requested tags used by the version's reports; null for the default.
     */
    public function guildTagIds(): ?array
    {
        return once(fn (): ?array => $this->idFilter('guild_tag_ids', fn (array $ids): array => $this->gameVersion()
            ->reports()->whereIn('guild_tag_id', $ids)->distinct()->orderBy('guild_tag_id')->pluck('guild_tag_id')->all()));
    }

    /**
     * The requested character, when it belongs to the current version.
     */
    public function character(): ?Character
    {
        return once(function (): ?Character {
            $characterId = $this->validated('character');

            return $characterId === null || ! $this->hasCountedAttendance()
                ? null
                : $this->gameVersion()->characters()->find($characterId);
        });
    }

    /**
     * The requested "since" date.
     */
    public function sinceDate(): ?CarbonImmutable
    {
        $date = $this->validated('since_date');

        return $date === null ? null : CarbonImmutable::parse($date);
    }

    /**
     * The requested "before" date.
     */
    public function beforeDate(): ?CarbonImmutable
    {
        $date = $this->validated('before_date');

        return $date === null ? null : CarbonImmutable::parse($date);
    }

    /**
     * Whether alts are folded into their mains; true unless the request turns it off.
     */
    public function combineLinkedCharacters(): bool
    {
        return $this->boolean('combine_linked_characters', true);
    }

    /**
     * A cache key unique to the version and the normalised filter values.
     */
    public function cacheKey(string $prefix): string
    {
        $filtersHash = hash('crc32', json_encode([
            'character_id' => $this->character()?->id,
            'rank_ids' => $this->rankIds(),
            'zone_ids' => $this->zoneIds(),
            'guild_tag_ids' => $this->guildTagIds(),
            'since_date' => $this->sinceDate()?->toDateString(),
            'before_date' => $this->beforeDate()?->toDateString(),
            'combine_linked_characters' => $this->combineLinkedCharacters(),
        ]));

        return "{$prefix}{$this->gameVersion()?->id}:{$filtersHash}";
    }

    /**
     * The filters as the Matrix page reads them. Id lists are null when their default applies.
     *
     * @return array{character: array{id: int, name: string}|null, rank_ids: array<int, int>|null, zone_ids: array<int, int>|null, guild_tag_ids: array<int, int>|null, since_date: string|null, before_date: string|null, combine_linked_characters: bool}
     */
    public function filters(): array
    {
        return [
            'character' => $this->displayedCharacter()?->only(['id', 'name']),
            'rank_ids' => $this->rankIds(),
            'zone_ids' => $this->zoneIds(),
            'guild_tag_ids' => $this->guildTagIds(),
            'since_date' => $this->validated('since_date'),
            'before_date' => $this->validated('before_date'),
            'combine_linked_characters' => $this->combineLinkedCharacters(),
        ];
    }

    /**
     * The day before the version's earliest report, as `Y-m-d`.
     */
    public function earliestDate(): ?string
    {
        return once(function (): ?string {
            return $this->hasCountedAttendance()
                ? FindReportDateRange::run($this->gameVersion())->earliest?->subDay()->toDateString()
                : null;
        });
    }

    /**
     * The character whose row the matrix shows: the requested character or, when
     * linked characters are combined, an alt's main.
     */
    private function displayedCharacter(): ?Character
    {
        $character = $this->character();

        if ($character === null || $character->is_main || ! $this->combineLinkedCharacters()) {
            return $character;
        }

        return $character->linkedCharacters()->where('is_main', true)->first() ?? $character;
    }

    /**
     * @param  Closure(array<int, int>): array<int, int>  $keepExisting
     * @return array<int, int>|null
     */
    private function idFilter(string $key, Closure $keepExisting): ?array
    {
        $value = $this->validated($key);

        if ($value === null || $value === 'all' || ! $this->hasCountedAttendance()) {
            return null;
        }

        if ($value === 'none') {
            return [];
        }

        return $keepExisting(array_map('intval', explode(',', $value)));
    }
}
