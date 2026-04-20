<?php

namespace App\Http\Requests\Raid;

use App\Services\Attendance\Filters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AttendanceMatrixRequest extends FormRequest
{
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return Filters::rules($this->all());
    }

    /**
     * Build a validated Filters DTO from the request input.
     */
    public function filters(): Filters
    {
        return Filters::fromArray($this->validated());
    }

    /**
     * The minimum date allowed for date filters, exposed so the view can render the date picker bounds.
     */
    public function resolveMinDate(): ?string
    {
        return Filters::resolveMinDate();
    }
}
