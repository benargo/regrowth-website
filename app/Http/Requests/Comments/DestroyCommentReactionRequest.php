<?php

namespace App\Http\Requests\Comments;

use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\CommentReaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DestroyCommentReactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $comment = $this->getComment();

        return $this->user()->can('react', $comment);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $comment = $this->getComment();
            $reaction = $this->getReaction();

            if (! $reaction || $reaction->comment_id !== $comment->id) {
                $validator->errors()->add('reaction', 'The reaction does not belong to this comment.');
            }
        });
    }

    /**
     * Get the comment from the route.
     */
    protected function getComment(): Comment
    {
        $comment = $this->route('comment');

        if ($comment instanceof Comment) {
            return $comment;
        }

        return Comment::findOrFail($comment);
    }

    /**
     * Get the reaction from the route.
     */
    protected function getReaction(): ?CommentReaction
    {
        $reaction = $this->route('reaction');

        if ($reaction instanceof CommentReaction) {
            return $reaction;
        }

        return CommentReaction::find($reaction);
    }
}
