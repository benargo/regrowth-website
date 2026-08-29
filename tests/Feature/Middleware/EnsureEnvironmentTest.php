<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\EnsureEnvironment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

#[Group('platform')]
class EnsureEnvironmentTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_calls_the_next_middleware_when_the_current_environment_is_allowed(): void
    {
        App::detectEnvironment(fn () => 'testing');

        $response = (new EnsureEnvironment)->handle(
            Request::create('/login/local'),
            fn () => response('next'),
            'testing',
        );

        $this->assertSame('next', $response->getContent());
    }

    #[Test]
    #[Group('happy-path')]
    public function it_calls_the_next_middleware_when_the_current_environment_matches_one_of_several_allowed(): void
    {
        App::detectEnvironment(fn () => 'local');

        $response = (new EnsureEnvironment)->handle(
            Request::create('/login/local'),
            fn () => response('next'),
            'local',
            'testing',
        );

        $this->assertSame('next', $response->getContent());
    }

    #[Test]
    #[Group('authorization')]
    public function it_aborts_with_404_when_the_current_environment_is_not_allowed(): void
    {
        App::detectEnvironment(fn () => 'production');

        $this->expectException(NotFoundHttpException::class);

        (new EnsureEnvironment)->handle(
            Request::create('/login/local'),
            fn () => response('next'),
            'local',
            'testing',
        );
    }

    #[Test]
    #[Group('authorization')]
    public function it_aborts_with_404_when_no_environments_are_passed(): void
    {
        App::detectEnvironment(fn () => 'local');

        $this->expectException(NotFoundHttpException::class);

        (new EnsureEnvironment)->handle(
            Request::create('/login/local'),
            fn () => response('next'),
        );
    }

    #[Test]
    public function it_matches_the_environment_name_exactly_without_trimming_whitespace(): void
    {
        App::detectEnvironment(fn () => 'local');

        $this->expectException(NotFoundHttpException::class);

        (new EnsureEnvironment)->handle(
            Request::create('/login/local'),
            fn () => response('next'),
            ' local ',
        );
    }

    #[Test]
    #[Group('authorization')]
    public function it_does_not_call_the_next_middleware_when_the_environment_is_not_allowed(): void
    {
        App::detectEnvironment(fn () => 'production');
        $nextCalled = false;

        try {
            (new EnsureEnvironment)->handle(
                Request::create('/login/local'),
                function () use (&$nextCalled) {
                    $nextCalled = true;

                    return response('next');
                },
                'local',
                'testing',
            );
        } catch (NotFoundHttpException) {
            // expected
        }

        $this->assertFalse($nextCalled);
    }
}
