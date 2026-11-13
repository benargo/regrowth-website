<?php

namespace Tests\Feature\Middleware;

use App\Actions\DesktopClient\RecordDesktopClient;
use App\Http\Middleware\EnsureDesktopClient;
use App\Models\DesktopClient;
use App\Models\DesktopClientToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\TransientToken;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

#[Group('auth')]
class EnsureDesktopClientTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[Group('authorization')]
    public function it_rejects_a_transient_cookie_token(): void
    {
        $user = User::factory()->make()->withAccessToken(new TransientToken);
        $request = $this->requestFor($user, [
            'X-Desktop-Client-Id' => fake()->uuid(),
            'X-Desktop-Client-Name' => 'Raid PC',
        ]);

        try {
            (new EnsureDesktopClient)->handle($request, fn () => response('ok'));
            $this->fail('Expected a 403 response.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    #[Test]
    #[Group('validation')]
    public function it_requires_a_uuid_device_id(): void
    {
        $request = $this->requestFor($this->bearerUser(), [
            'X-Desktop-Client-Id' => 'not-a-uuid',
            'X-Desktop-Client-Name' => 'Raid PC',
        ]);

        $response = (new EnsureDesktopClient)->handle($request, fn () => response('ok'));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            ['message' => 'Missing or invalid X-Desktop-Client-Id or X-Desktop-Client-Name.'],
            json_decode($response->getContent(), true),
        );
    }

    #[Test]
    #[Group('validation')]
    public function it_requires_a_name_of_at_most_100_characters(): void
    {
        foreach (['', str_repeat('a', 101)] as $name) {
            $request = $this->requestFor($this->bearerUser(), [
                'X-Desktop-Client-Id' => fake()->uuid(),
                'X-Desktop-Client-Name' => $name,
            ]);

            $response = (new EnsureDesktopClient)->handle($request, fn () => response('ok'));

            $this->assertSame(400, $response->getStatusCode());
        }
    }

    #[Test]
    #[Group('happy-path')]
    public function it_records_nothing(): void
    {
        RecordDesktopClient::shouldNotRun();

        $binding = DesktopClientToken::factory()->create();
        $request = $this->requestFor($this->boundUser($binding), [
            'X-Desktop-Client-Id' => $binding->desktop_client_id,
            'X-Desktop-Client-Name' => 'Raid PC',
        ]);

        $response = (new EnsureDesktopClient)->handle($request, fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
        $this->assertFalse($request->attributes->has('desktopClient'));
    }

    // ==================== token binding ====================

    #[Test]
    #[Group('happy-path')]
    public function it_passes_a_token_bound_to_this_device(): void
    {
        $binding = DesktopClientToken::factory()->create();
        $request = $this->requestFor($this->boundUser($binding), [
            'X-Desktop-Client-Id' => $binding->desktop_client_id,
            'X-Desktop-Client-Name' => 'Raid PC',
        ]);

        $response = (new EnsureDesktopClient)->handle($request, fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    #[Test]
    #[Group('authorization')]
    public function it_refuses_a_token_used_with_another_device_id(): void
    {
        $binding = DesktopClientToken::factory()->create();
        $user = $this->boundUser($binding);
        $otherDevice = DesktopClient::factory()->create(['user_id' => $user->id]);

        $response = $this->handleWithDeviceId($user, $otherDevice->id);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(['message' => 'Unauthenticated.'], json_decode($response->getContent(), true));
    }

    #[Test]
    #[Group('authorization')]
    public function it_refuses_an_unbound_token(): void
    {
        $desktopClient = DesktopClient::factory()->create();

        $response = $this->handleWithDeviceId($this->bearerUser(), $desktopClient->id);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(['message' => 'Unauthenticated.'], json_decode($response->getContent(), true));
    }

    #[Test]
    #[Group('authorization')]
    public function it_answers_a_foreign_device_id_like_any_mismatch(): void
    {
        $user = $this->boundUser(DesktopClientToken::factory()->create());
        $foreignDevice = DesktopClient::factory()->create();

        $foreignResponse = $this->handleWithDeviceId($user, $foreignDevice->id);
        $randomResponse = $this->handleWithDeviceId($user, fake()->uuid());

        $this->assertSame(401, $foreignResponse->getStatusCode());
        $this->assertSame($randomResponse->getStatusCode(), $foreignResponse->getStatusCode());
        $this->assertSame($randomResponse->getContent(), $foreignResponse->getContent());
    }

    // ==================== helpers ====================

    /**
     * @param  array<string, string>  $headers
     */
    private function requestFor(User $user, array $headers): Request
    {
        $request = Request::create('/test-route');
        $request->setUserResolver(fn () => $user);

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }

    private function handleWithDeviceId(User $user, string $deviceId): Response
    {
        $request = $this->requestFor($user, [
            'X-Desktop-Client-Id' => $deviceId,
            'X-Desktop-Client-Name' => 'Raid PC',
        ]);

        return (new EnsureDesktopClient)->handle($request, fn () => response('ok'));
    }

    private function bearerUser(): User
    {
        return User::factory()->make()->withAccessToken(
            new AccessToken(['oauth_access_token_id' => 'abc', 'oauth_scopes' => ['grm:upload']])
        );
    }

    private function boundUser(DesktopClientToken $binding): User
    {
        return $binding->desktopClient->user->withAccessToken(
            new AccessToken(['oauth_access_token_id' => $binding->access_token_id, 'oauth_scopes' => ['grm:upload']])
        );
    }
}
