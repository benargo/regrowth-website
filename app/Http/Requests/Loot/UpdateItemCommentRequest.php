<?php

namespace App\Http\Requests\Loot;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $comment = $this->route('comment');

        return $this->user()->can('edit-loot-comment', $comment);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:3', 'max:5000'],
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
        ];
    }
}
