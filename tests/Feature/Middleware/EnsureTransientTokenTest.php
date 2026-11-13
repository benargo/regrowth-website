<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\EnsureTransientToken;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\TransientToken;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

#[Group('auth')]
class EnsureTransientTokenTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_passes_a_transient_token(): void
    {
        $user = User::factory()->make()->withAccessToken(new TransientToken);
        $request = Request::create('/test-route');
        $request->setUserResolver(fn () => $user);

        $response = (new EnsureTransientToken)->handle($request, fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    #[Test]
    #[Group('happy-path')]
    public function it_passes_a_guest(): void
    {
        $response = (new EnsureTransientToken)->handle(Request::create('/test-route'), fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    #[Test]
    #[Group('authorization')]
    public function it_rejects_a_bearer_token(): void
    {
        $user = User::factory()->make()->withAccessToken(
            new AccessToken(['oauth_access_token_id' => 'abc', 'oauth_scopes' => ['grm:upload']])
        );
        $request = Request::create('/test-route');
        $request->setUserResolver(fn () => $user);

        try {
            (new EnsureTransientToken)->handle($request, fn () => response('ok'));
            $this->fail('Expected a 403 response.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
