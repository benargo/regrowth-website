<?php

namespace App\Http\Requests\Raid;

use App\Models\Raids\Report;
use App\Traits\ParsesFilterParam;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;

class ReportsIndexRequest extends FormRequest
{
    use ParsesFilterParam;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Report::class) ?? false;
    }

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

        if ($this->filled('before_date')) {
            $sinceDateRules[] = 'before_or_equal:before_date';
        }
        if ($this->filled('since_date')) {
            $beforeDateRules[] = 'after_or_equal:since_date';
        }

        return [
            'zone_ids' => ['nullable', 'string', 'regex:/^(all|none|\d+(,\d+)*)$/'],
            'guild_tag_ids' => ['nullable', 'string', 'regex:/^(all|none|\d+(,\d+)*)$/'],
            'days' => ['nullable', 'string', 'regex:/^(all|none|[0-6](,[0-6])*)$/'],
            'since_date' => $sinceDateRules,
            'before_date' => $beforeDateRules,
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

    public function days(): ?array
    {
        return $this->parseFilterParam('days');
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
