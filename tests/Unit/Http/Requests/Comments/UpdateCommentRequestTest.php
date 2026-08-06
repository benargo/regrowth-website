<?php

namespace Tests\Unit\Http\Requests\Comments;

use App\Http\Requests\Comments\UpdateCommentRequest;
use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('comments')]
class UpdateCommentRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(array $data, Comment $comment, bool $canUpdate, bool $canResolve): UpdateCommentRequest
    {
        $request = UpdateCommentRequest::create('/', 'PATCH', $data);

        $user = Mockery::mock();
        $user->shouldReceive('can')->with('update', $comment)->andReturn($canUpdate)->byDefault();
        $user->shouldReceive('can')->with('markAsResolved', $comment)->andReturn($canResolve)->byDefault();

        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(function () use ($comment) {
            $route = Mockery::mock();
            $route->shouldReceive('parameter')->with('comment', null)->andReturn($comment);

            return $route;
        });

        return $request;
    }

    // ==================== rules ====================

    #[Test]
    public function body_is_optional(): void
    {
        $rules = UpdateCommentRequest::create('/', 'PATCH', [])->rules();

        $this->assertArrayHasKey('body', $rules);
        $this->assertContains('sometimes', $rules['body']);
        $this->assertContains('min:3', $rules['body']);
        $this->assertContains('max:5000', $rules['body']);
        $this->assertNotContains('required', $rules['body']);
    }

    #[Test]
    public function is_resolved_is_optional_and_boolean(): void
    {
        $rules = UpdateCommentRequest::create('/', 'PATCH', [])->rules();

        $this->assertArrayHasKey('isResolved', $rules);
        $this->assertContains('sometimes', $rules['isResolved']);
        $this->assertContains('boolean', $rules['isResolved']);
        $this->assertNotContains('required', $rules['isResolved']);
    }

    // ==================== authorize ====================

    #[Test]
    public function authorize_requires_only_update_when_is_resolved_is_absent(): void
    {
        $comment = Comment::factory()->create();

        $request = $this->makeRequest(['body' => 'An edited body'], $comment, canUpdate: true, canResolve: false);

        $this->assertTrue($request->authorize());
    }

    #[Group('authorization')]
    #[Test]
    public function authorize_denies_when_update_is_denied(): void
    {
        $comment = Comment::factory()->create();

        $request = $this->makeRequest(['body' => 'An edited body'], $comment, canUpdate: false, canResolve: true);

        $this->assertFalse($request->authorize());
    }

    #[Group('authorization')]
    #[Test]
    public function authorize_additionally_requires_mark_as_resolved_when_is_resolved_is_present(): void
    {
        $comment = Comment::factory()->create();

        $request = $this->makeRequest(['isResolved' => true], $comment, canUpdate: true, canResolve: false);

        $this->assertFalse($request->authorize());
    }

    #[Test]
    public function authorize_allows_resolving_when_both_abilities_are_granted(): void
    {
        $comment = Comment::factory()->create();

        $request = $this->makeRequest(['isResolved' => true], $comment, canUpdate: true, canResolve: true);

        $this->assertTrue($request->authorize());
    }

    #[Group('authorization')]
    #[Test]
    public function authorize_checks_mark_as_resolved_even_when_un_resolving(): void
    {
        $comment = Comment::factory()->resolved()->create();

        $request = $this->makeRequest(['isResolved' => false], $comment, canUpdate: true, canResolve: false);

        $this->assertFalse($request->authorize());
    }
}
