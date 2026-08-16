<?php

namespace Tests\Unit\Http\Requests\Comments;

use App\Http\Requests\Comments\StoreReactionRequest;
use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('comments')]
class StoreReactionRequestTest extends TestCase
{
    use RefreshDatabase;

    // ==================== rules ====================

    #[Test]
    public function comment_id_is_required_and_must_exist(): void
    {
        $rules = $this->makeRequest([])->rules();

        $this->assertArrayHasKey('comment_id', $rules);
        $this->assertContains('required', $rules['comment_id']);
        $this->assertContains('integer', $rules['comment_id']);
        $this->assertContains('exists:comments,id', $rules['comment_id']);
    }

    // ==================== comment() ====================

    #[Test]
    public function comment_returns_the_resolved_model(): void
    {
        $comment = Comment::factory()->create();

        $request = $this->makeRequest(['comment_id' => $comment->id]);

        $this->assertTrue($request->comment()->is($comment));
    }

    private function makeRequest(array $data): StoreReactionRequest
    {
        return StoreReactionRequest::create('/', 'POST', $data);
    }
}
