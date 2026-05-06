<?php

namespace App\Http\Requests\Raid;

use App\Models\Raids\Report;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;

class ReportsIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $minDate = $this->resolveMinDate();
        $today = Carbon::today(config('app.timezone'))->toDateString();

        $dateRules = ['nullable', 'date', 'before_or_equal:'.$today];

        if ($minDate !== null) {
            $dateRules[] = 'after_or_equal:'.$minDate;
        }

        $sinceDateRules = $dateRules;
        $beforeDateRules = $dateRules;

        if ($this->filled('filter.before_date')) {
            $sinceDateRules[] = 'before_or_equal:filter.before_date';
        }
        if ($this->filled('filter.since_date')) {
            $beforeDateRules[] = 'after_or_equal:filter.since_date';
        }

        return [
            'filter.zone_ids' => ['nullable', 'string', 'regex:/^(\d+(,\d+)*)$/'],
            'filter.guild_tag_ids' => ['nullable', 'string', 'regex:/^(\d+(,\d+)*)$/'],
            'filter.days' => ['nullable', 'string', 'regex:/^([0-6](,[0-6])*)$/'],
            'filter.since_date' => $sinceDateRules,
            'filter.before_date' => $beforeDateRules,
        ];
    }

    /**
     * Resolve the minimum allowed date for date filters, which is one day
     * before the earliest report in the database.
     */
    public function resolveMinDate(): ?string
    {
        $earliestRaw = Cache::tags(['reports'])->remember(
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
}
