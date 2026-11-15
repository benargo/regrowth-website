<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\AddServerTimeHeader;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class AddServerTimeHeaderTest extends TestCase
{
    #[Test]
    public function it_sets_the_server_time_header_to_the_current_unix_time(): void
    {
        $this->freezeTime();

        $response = (new AddServerTimeHeader)->handle(new Request, fn () => response('ok', 403));

        $this->assertSame((string) now()->getTimestamp(), $response->headers->get('X-Server-Time'));
        $this->assertSame(403, $response->getStatusCode());
    }
}
