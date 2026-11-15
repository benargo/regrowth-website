<?php

namespace Tests\Feature\Middleware;

use App\Actions\DesktopClient\RecordDesktopClient;
use App\Http\Middleware\TouchDesktopClient;
use App\Models\DesktopClient;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class TouchDesktopClientTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_records_the_device_with_the_access_token_id_and_passes_it_on(): void
    {
        $id = fake()->uuid();
        $client = DesktopClient::factory()->make(['user_id' => '11']);

        RecordDesktopClient::shouldRun()
            ->once()
            ->withArgs(fn ($user, $clientId, $name, $tokenId) => $clientId === $id && $name === 'Raid PC' && $tokenId === 'abc')
            ->andReturn($client);

        $request = $this->requestFor($this->bearerUser(), [
            'X-Desktop-Client-Id' => $id,
            'X-Desktop-Client-Name' => 'Raid PC',
        ]);

        $response = (new TouchDesktopClient)->handle($request, fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
        $this->assertSame($client, $request->attributes->get('desktopClient'));
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

    private function bearerUser(): User
    {
        return User::factory()->make()->withAccessToken(
            new AccessToken(['oauth_access_token_id' => 'abc', 'oauth_scopes' => ['grm:upload']])
        );
    }
}
