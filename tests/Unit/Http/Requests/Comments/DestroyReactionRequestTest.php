<?php

namespace Tests\Unit\Http\Requests\Comments;

use App\Http\Requests\Comments\DestroyReactionRequest;
use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\CommentReaction;
use Illuminate\Routing\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DestroyReactionRequestTest extends TestCase
{
    private function makeRequest(): DestroyReactionRequest
    {
        return DestroyReactionRequest::create('/', 'DELETE');
    }

    // ==================== rules ====================

    #[Test]
    public function rules_returns_empty_array(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertSame([], $rules);
    }

    // ==================== withValidator ====================

    #[Test]
    public function with_validator_passes_when_reaction_belongs_to_comment(): void
    {
        $comment = \Mockery::mock(Comment::class);
        $comment->allows('getAttribute')->with('id')->andReturn(1);

        $reaction = \Mockery::mock(CommentReaction::class);
        $reaction->allows('getAttribute')->with('comment_id')->andReturn(1);

        $request = $this->makeRequest();
        $route = \Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('comment', null)->andReturn($comment);
        $route->shouldReceive('parameter')->with('reaction', null)->andReturn($reaction);
        $request->setRouteResolver(fn () => $route);

        $validator = \Mockery::mock(Validator::class);
        $validator->shouldReceive('after')->once()->andReturnUsing(function (callable $callback) use ($validator) {
            $callback($validator);
        });
        $validator->shouldNotReceive('errors');

        $request->withValidator($validator);
    }

    #[Test]
    public function with_validator_adds_error_when_reaction_does_not_belong_to_comment(): void
    {
        $comment = \Mockery::mock(Comment::class);
        $comment->allows('getAttribute')->with('id')->andReturn(1);

        $reaction = \Mockery::mock(CommentReaction::class);
        $reaction->allows('getAttribute')->with('comment_id')->andReturn(2);

        $request = $this->makeRequest();
        $route = \Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('comment', null)->andReturn($comment);
        $route->shouldReceive('parameter')->with('reaction', null)->andReturn($reaction);
        $request->setRouteResolver(fn () => $route);

        $errors = \Mockery::mock(MessageBag::class);
        $errors->shouldReceive('add')->once()->with('reaction', 'The reaction does not belong to this comment.');

        $validator = \Mockery::mock(Validator::class);
        $validator->shouldReceive('after')->once()->andReturnUsing(function (callable $callback) use ($validator) {
            $callback($validator);
        });
        $validator->shouldReceive('errors')->once()->andReturn($errors);

        $request->withValidator($validator);
    }

    #[Test]
    public function with_validator_adds_error_when_reaction_is_null(): void
    {
        $comment = \Mockery::mock(Comment::class);
        $comment->allows('getAttribute')->with('id')->andReturn(1);

        $request = new class extends DestroyReactionRequest
        {
            protected function getComment(): Comment
            {
                $comment = \Mockery::mock(Comment::class);
                $comment->allows('getAttribute')->with('id')->andReturn(1);

                return $comment;
            }

            protected function getReaction(): ?CommentReaction
            {
                return null;
            }
        };

        $errors = \Mockery::mock(MessageBag::class);
        $errors->shouldReceive('add')->once()->with('reaction', 'The reaction does not belong to this comment.');

        $validator = \Mockery::mock(Validator::class);
        $validator->shouldReceive('after')->once()->andReturnUsing(function (callable $callback) use ($validator) {
            $callback($validator);
        });
        $validator->shouldReceive('errors')->once()->andReturn($errors);

        $request->withValidator($validator);
    }
}
