<?php

namespace App\Http\Requests\Raid;

use App\Models\WarcraftLogs\Report;
use App\Traits\ParsesFilterParam;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class AttendanceMatrixRequest extends FormRequest
{
    use ParsesFilterParam;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('view-attendance');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $minDate = $this->resolveMinDate();
        $today = Carbon::today(config('app.timezone', 'UTC'))->toDateString();

        $dateRules = ['nullable', 'date', 'before_or_equal:'.$today];

        if ($minDate !== null) {
            $dateRules[] = 'after_or_equal:'.$minDate;
        }

        // Derive specific rules for the range boundaries so we can enforce ordering
        $sinceDateRules = $dateRules;
        $beforeDateRules = $dateRules;

        // Ensure since_date is not after before_date when both are present
        if ($this->filled('before_date')) {
            $sinceDateRules[] = 'before_or_equal:before_date';
        }
        if ($this->filled('since_date')) {
            $beforeDateRules[] = 'after_or_equal:since_date';
        }

        return [
            'rank_ids' => ['nullable', 'string', 'regex:/^(all|none|\d+(,\d+)*)$/'],
            'zone_ids' => ['nullable', 'string', 'regex:/^(all|none|\d+(,\d+)*)$/'],
            'guild_tag_ids' => ['nullable', 'string', 'regex:/^(all|none|\d+(,\d+)*)$/'],
            'since_date' => $sinceDateRules,
            'before_date' => $beforeDateRules,
            'combine_linked_characters' => ['nullable', 'boolean'],
        ];
    }

    public function zoneIds(): ?array
    {
        return $this->parseFilterParam('zone_ids');
    }

    public function guildTagIds(): ?array
    {
        return $this->parseFilterParam('guild_tag_ids');
    }

    public function rankIds(): ?array
    {
        return $this->parseFilterParam('rank_ids');
    }

    public function combineLinkedCharacters(): bool
    {
        return $this->boolean('combine_linked_characters', true);
    }

    /**
     * Resolve the minimum allowed date for date filters, which is one day
     * before the earliest report in the database.
     */
    private function resolveMinDate(): ?string
    {
        $earliestRaw = Cache::tags('warcraftlogs')->remember(
            'attendance_matrix_earliest_date',
            now()->addDay(),
            fn () => Report::min('start_time'),
        );

        if ($earliestRaw === null) {
            return null;
        }

        return Carbon::parse($earliestRaw, 'UTC')
            ->timezone(config('app.timezone', 'UTC'))
            ->subDay()
            ->toDateString();
    }
}
