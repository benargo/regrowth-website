<?php

namespace App\Http\Requests\Comments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Setting `isResolved` requires `markAsResolved` in addition to `update`:
     * passing `update` alone would otherwise let a raider resolve their own
     * comment. `has()` rather than `filled()`, so un-resolving is gated too.
     */
    public function authorize(): bool
    {
        $comment = $this->route('comment');

        if ($this->has('isResolved')) {
            return $this->user()->can('update', $comment)
                && $this->user()->can('markAsResolved', $comment);
        }

        return $this->user()->can('update', $comment);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['sometimes', 'string', 'min:3', 'max:5000'],
            'isResolved' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Please enter a comment.',
            'body.min' => 'Comment must be at least 3 characters.',
            'body.max' => 'Comment must not exceed 5000 characters.',
            'isResolved.boolean' => 'Invalid value for resolved status.',
        ];
    }
}
