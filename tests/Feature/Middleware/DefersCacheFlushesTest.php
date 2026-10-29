<?php

namespace Tests\Feature\Middleware;

use App\Contracts\Http\Middleware\SharesDeferredCacheFlushes;
use App\Http\Middleware\DefersCacheFlushes;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[Group('platform')]
class DefersCacheFlushesTest extends TestCase
{
    #[Group('happy-path')]
    #[Test]
    public function it_returns_the_next_response(): void
    {
        $response = (new DefersCacheFlushes)->handle(Request::create('/'), fn () => response('next'));

        $this->assertSame('next', $response->getContent());
    }

    #[Test]
    public function it_marks_the_request_as_deferring_only_while_the_next_middleware_runs(): void
    {
        $request = Request::create('/');
        $wasDeferring = null;

        (new DefersCacheFlushes)->handle($request, function (Request $request) use (&$wasDeferring) {
            $wasDeferring = $request->attributes->get(SharesDeferredCacheFlushes::DEFERRING_KEY);

            return response('next');
        });

        $this->assertTrue($wasDeferring);
        $this->assertFalse($request->attributes->has(SharesDeferredCacheFlushes::DEFERRING_KEY));
    }

    #[Group('happy-path')]
    #[Test]
    public function it_runs_each_pending_flush_once_after_the_next_middleware(): void
    {
        $request = Request::create('/');
        $calls = ['raiding' => 0, 'game-versions' => 0];

        (new DefersCacheFlushes)->handle($request, function (Request $request) use (&$calls) {
            $request->attributes->set(SharesDeferredCacheFlushes::PENDING_KEY, [
                'raiding' => function () use (&$calls): void {
                    $calls['raiding']++;
                },
                'game-versions' => function () use (&$calls): void {
                    $calls['game-versions']++;
                },
            ]);

            $this->assertSame(['raiding' => 0, 'game-versions' => 0], $calls);

            return response('next');
        });

        $this->assertSame(['raiding' => 1, 'game-versions' => 1], $calls);
        $this->assertFalse($request->attributes->has(SharesDeferredCacheFlushes::PENDING_KEY));
    }

    #[Group('failure-path')]
    #[Test]
    public function it_runs_pending_flushes_when_the_next_middleware_throws(): void
    {
        $request = Request::create('/');
        $calls = 0;

        try {
            (new DefersCacheFlushes)->handle($request, function (Request $request) use (&$calls) {
                $request->attributes->set(SharesDeferredCacheFlushes::PENDING_KEY, [
                    'raiding' => function () use (&$calls): void {
                        $calls++;
                    },
                ]);

                throw new RuntimeException('Write failed.');
            });
        } catch (RuntimeException) {
            // Expected; the assertions below cover the flush.
        }

        $this->assertSame(1, $calls);
        $this->assertFalse($request->attributes->has(SharesDeferredCacheFlushes::DEFERRING_KEY));
    }
}
