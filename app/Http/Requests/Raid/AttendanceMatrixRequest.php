<?php

namespace App\Http\Requests\Raid;

use App\Models\WarcraftLogs\Report;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class AttendanceMatrixRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('view-attendance-dashboard');
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

        return [
            'zone_ids' => ['nullable', 'array'],
            'zone_ids.*' => ['integer'],
            'guild_tag_ids' => ['nullable', 'array'],
            'guild_tag_ids.*' => ['integer'],
            'since_date' => $dateRules,
            'before_date' => $dateRules,
        ];
    }

    /**
     * Resolve the minimum allowed date for date filters, which is one day
     * before the earliest report in the database.
     */
    private function resolveMinDate(): ?string
    {
        $earliestRaw = Cache::tags('warcraftlogs')->remember(
            'attendance_matrix_earliest_date',
            now()->addDays(7),
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
