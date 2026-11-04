<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\PreventIndexingAndCaching;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class PreventIndexingAndCachingTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_marks_the_response_as_not_to_be_indexed_or_stored(): void
    {
        $response = (new PreventIndexingAndCaching)->handle(
            Request::create('/discord'),
            fn () => response('next'),
        );

        $this->assertSame('next', $response->getContent());
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }
}
