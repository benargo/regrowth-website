<?php

namespace App\Services\Attendance;

use App\Models\Character;
use App\Models\GuildTag;
use App\Models\Raids\Report;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Data;

class FiltersData extends Data
{
    public function __construct(
        public ?Character $character = null,
        /** @var array<int, int> */
        public array $rankIds = [],
        /** @var array<int, int>|null null means no zone filter; [] means filter for no zones */
        public ?array $zoneIds = null,
        /** @var array<int, int> */
        public array $guildTagIds = [],
        public ?Carbon $sinceDate = null,
        public ?Carbon $beforeDate = null,
        public bool $includeLinkedCharacters = false,
    ) {}

    /**
     * @return array{character: array{id: int, name: string}|null, rank_ids: array<int, int>, zone_ids: array<int, int>|null, guild_tag_ids: array<int, int>, since_date: string|null, before_date: string|null, combine_linked_characters: bool}
     */
    public function toArray(): array
    {
        return [
            'character' => $this->character?->only(['id', 'name']),
            'rank_ids' => $this->rankIds,
            'zone_ids' => $this->zoneIds,
            'guild_tag_ids' => $this->guildTagIds,
            'since_date' => $this->sinceDate?->format('Y-m-d'),
            'before_date' => $this->beforeDate?->format('Y-m-d'),
            'combine_linked_characters' => $this->includeLinkedCharacters,
        ];
    }

    /**
     * @return array{character: array{id: int, name: string}|null, rank_ids: array<int, int>, zone_ids: array<int, int>|null, guild_tag_ids: array<int, int>, since_date: string|null, before_date: string|null, combine_linked_characters: bool}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Validate the raw input and return a populated FiltersData instance.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public static function validateInput(array $input): self
    {
        $validated = Validator::make($input, self::rules($input))->validate();

        return self::fromArray($validated);
    }

    /**
     * Hydrate a FiltersData instance from an input array (assumes validation has already passed).
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $character = ! empty($input['character']) ? Character::find($input['character']) : null;

        $rankIds = self::parseCsv($input, 'rank_ids') ?? [];
        $zoneIds = self::parseCsv($input, 'zone_ids');

        $guildTagIds = self::parseCsv($input, 'guild_tag_ids')
            ?? GuildTag::where('count_attendance', true)->pluck('id')->toArray();

        $sinceDate = ! empty($input['since_date'])
            ? Carbon::parse($input['since_date'], config('app.timezone'))->addDay()->setTime(5, 0, 0)->utc()
            : null;

        $beforeDate = ! empty($input['before_date'])
            ? Carbon::parse($input['before_date'], config('app.timezone'))->setTime(5, 0, 0)->utc()
            : null;

        $combineLinkedCharacters = array_key_exists('combine_linked_characters', $input)
            ? filter_var($input['combine_linked_characters'], FILTER_VALIDATE_BOOLEAN)
            : true;

        return new self(
            character: $character,
            rankIds: $rankIds,
            zoneIds: $zoneIds,
            guildTagIds: $guildTagIds,
            sinceDate: $sinceDate,
            beforeDate: $beforeDate,
            includeLinkedCharacters: $combineLinkedCharacters,
        );
    }

    /**
     * Validation rules for raw filter input.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, array<int, string>>
     */
    public static function rules(array $input = []): array
    {
        $minDate = self::resolveMinDate();
        $today = Carbon::today(config('app.timezone'))->toDateString();

        $dateRules = ['nullable', 'date', 'before_or_equal:'.$today];

        if ($minDate !== null) {
            $dateRules[] = 'after_or_equal:'.$minDate;
        }

        $sinceDateRules = $dateRules;
        $beforeDateRules = $dateRules;

        if (! empty($input['before_date'])) {
            $sinceDateRules[] = 'before_or_equal:before_date';
        }

        if (! empty($input['since_date'])) {
            $beforeDateRules[] = 'after_or_equal:since_date';
        }

        return [
            'character' => ['nullable', 'integer', 'exists:characters,id'],
            'rank_ids' => ['nullable', 'string', 'regex:/^(all|none|\d+(,\d+)*)$/'],
            'zone_ids' => ['nullable', 'string', 'regex:/^(all|none|\d+(,\d+)*)$/'],
            'guild_tag_ids' => ['nullable', 'string', 'regex:/^(all|none|\d+(,\d+)*)$/'],
            'since_date' => $sinceDateRules,
            'before_date' => $beforeDateRules,
            'combine_linked_characters' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Generate a deterministic cache key based on the filters that affect which reports are loaded.
     */
    public function cacheKey(string $prefix): string
    {
        $zoneIds = $this->zoneIds;

        if ($zoneIds !== null) {
            sort($zoneIds);
        }

        $guildTagIds = $this->guildTagIds;
        sort($guildTagIds);

        $rankIds = $this->rankIds;
        sort($rankIds);

        $payload = [
            'character_id' => $this->character?->id,
            'zone_ids' => $zoneIds,
            'guild_tag_ids' => $guildTagIds,
            'rank_ids' => $rankIds,
            'since_date' => $this->sinceDate?->toISOString(),
            'before_date' => $this->beforeDate?->toISOString(),
            'combine_linked_characters' => $this->includeLinkedCharacters,
        ];

        return $prefix.hash('crc32', json_encode($payload));
    }

    /**
     * Resolve the minimum allowed date for date filters, which is one day before the earliest report in the database.
     */
    public static function resolveMinDate(): ?string
    {
        $earliestRaw = Cache::tags(['attendance', 'reports'])->remember(
            'reports:earliest_date',
            now()->addDay(),
            fn () => Report::min('start_time'),
        );

        if ($earliestRaw === null) {
            return null;
        }

        return Carbon::parse($earliestRaw, 'UTC')
            ->timezone(config('app.timezone'))
            ->subDay()
            ->toDateString();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, int>|null
     */
    private static function parseCsv(array $input, string $key): ?array
    {
        if (! array_key_exists($key, $input)) {
            return null;
        }

        $value = $input[$key];

        if ($value === 'none') {
            return [];
        }

        if ($value === null || $value === 'all') {
            return null;
        }

        return array_map('intval', explode(',', (string) $value));
    }
}
