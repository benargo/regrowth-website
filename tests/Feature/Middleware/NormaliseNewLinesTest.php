<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\NormaliseNewLines;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class NormaliseNewLinesTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_converts_carriage_returns_to_line_feeds(): void
    {
        $request = $this->handle(Request::create('/test-route', 'POST', ['content' => "a\r\nb\rc\nd"]));

        $this->assertSame("a\nb\nc\nd", $request->input('content'));
    }

    #[Test]
    public function it_converts_line_endings_in_nested_input(): void
    {
        $request = $this->handle(Request::create('/test-route', 'POST', ['chapters' => [['content' => "a\r\nb"]]]));

        $this->assertSame("a\nb", $request->input('chapters.0.content'));
    }

    #[Test]
    public function it_leaves_other_whitespace_alone(): void
    {
        $request = $this->handle(Request::create('/test-route', 'POST', ['content' => "  a \t\n\n"]));

        $this->assertSame("  a \t\n\n", $request->input('content'));
    }

    // ==================== helpers ====================

    private function handle(Request $request): Request
    {
        (new NormaliseNewLines)->handle($request, fn () => response('next'));

        return $request;
    }
}
