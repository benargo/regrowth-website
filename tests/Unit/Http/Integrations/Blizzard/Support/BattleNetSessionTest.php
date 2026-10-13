<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Support;

use App\Http\Integrations\Blizzard\Exceptions\ApiException;
use App\Http\Integrations\Blizzard\Exceptions\BattleNetSessionException;
use App\Http\Integrations\Blizzard\Support\BattleNetSession;
use App\Http\Integrations\Blizzard\Support\BattleNetSessionError;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Auth\AccessTokenAuthenticator;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Tests\TestCase;

#[Group('auth')]
#[Group('blizzard-integration')]
class BattleNetSessionTest extends TestCase
{
    private Store $store;

    private BattleNetSession $battleNet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new Store('test', new ArraySessionHandler(10));
        $this->battleNet = new BattleNetSession($this->store);
    }

    // ==================== access token ====================

    #[Test]
    public function it_has_no_access_token_without_an_authenticator(): void
    {
        $this->assertNull($this->battleNet->accessToken());
        $this->assertFalse($this->battleNet->isConnected());
    }

    #[Test]
    public function it_has_no_access_token_once_the_authenticator_expires(): void
    {
        $this->battleNet->store($this->authenticator(expired: true));

        $this->assertNull($this->battleNet->accessToken());
        $this->assertFalse($this->battleNet->isConnected());
    }

    #[Test]
    public function it_returns_the_stored_access_token_while_valid(): void
    {
        $this->battleNet->store($this->authenticator());

        $this->assertSame('user-token', $this->battleNet->accessToken());
        $this->assertTrue($this->battleNet->isConnected());
        $this->assertInstanceOf(AccessTokenAuthenticator::class, $this->store->get(BattleNetSession::AUTHENTICATOR_KEY));
    }

    // ==================== withAccessToken ====================

    #[Test]
    #[Group('happy-path')]
    public function with_access_token_passes_the_token_and_returns_the_result(): void
    {
        $this->battleNet->store($this->authenticator());

        $result = $this->battleNet->withAccessToken(fn (string $token): string => "fetched with {$token}");

        $this->assertSame('fetched with user-token', $result);
    }

    #[Test]
    #[Group('error-handling')]
    public function with_access_token_asks_to_reconnect_without_a_token(): void
    {
        $this->assertSessionError(BattleNetSessionError::Reconnect, fn () => $this->battleNet->withAccessToken(fn (): never => $this->fail('The callback should not run.')));
    }

    #[Test]
    #[Group('error-handling')]
    public function with_access_token_asks_to_reconnect_and_forgets_a_rejected_token(): void
    {
        $this->battleNet->store($this->authenticator());

        $this->assertSessionError(BattleNetSessionError::Reconnect, fn () => $this->battleNet->withAccessToken(fn (): never => throw $this->apiException(401)));

        $this->assertFalse($this->store->has(BattleNetSession::AUTHENTICATOR_KEY));
    }

    #[Test]
    #[Group('error-handling')]
    public function with_access_token_reports_unavailable_and_keeps_the_token_on_a_server_error(): void
    {
        Log::shouldReceive('warning')->once()->with('Battle.net request failed.', ['status' => 500]);
        $this->battleNet->store($this->authenticator());

        $this->assertSessionError(BattleNetSessionError::Unavailable, fn () => $this->battleNet->withAccessToken(fn (): never => throw $this->apiException(500)));

        $this->assertTrue($this->store->has(BattleNetSession::AUTHENTICATOR_KEY));
    }

    #[Test]
    #[Group('error-handling')]
    public function with_access_token_reports_unavailable_when_battle_net_is_unreachable(): void
    {
        Log::shouldReceive('warning')->once()->with('Battle.net request failed.', ['message' => 'Connection refused']);
        $this->battleNet->store($this->authenticator());
        $exception = new FatalRequestException(new RuntimeException('Connection refused'), $this->createStub(PendingRequest::class));

        $this->assertSessionError(BattleNetSessionError::Unavailable, fn () => $this->battleNet->withAccessToken(fn (): never => throw $exception));
    }

    // ==================== authorization state ====================

    #[Test]
    public function start_authorization_stores_and_returns_a_random_state(): void
    {
        $state = $this->battleNet->startAuthorization();

        $this->assertSame(32, strlen($state));
        $this->assertSame($state, $this->store->get(BattleNetSession::STATE_KEY));
    }

    #[Test]
    public function verify_state_accepts_the_stored_state_once(): void
    {
        $state = $this->battleNet->startAuthorization();

        $this->assertTrue($this->battleNet->verifyState($state));
        $this->assertFalse($this->store->has(BattleNetSession::STATE_KEY));
        $this->assertFalse($this->battleNet->verifyState($state));
    }

    #[Test]
    #[Group('validation')]
    public function verify_state_rejects_a_mismatched_or_missing_state(): void
    {
        $this->battleNet->startAuthorization();
        $this->assertFalse($this->battleNet->verifyState('wrong-state'));

        $this->assertFalse($this->battleNet->verifyState(null));

        $this->store->put(BattleNetSession::STATE_KEY, '');
        $this->assertFalse($this->battleNet->verifyState(''));
    }

    // ==================== intended url ====================

    #[Test]
    public function it_remembers_and_pulls_the_intended_url(): void
    {
        $this->battleNet->rememberIntendedUrl('https://regrowth.test/account/absences/create');

        $this->assertSame('https://regrowth.test/account/absences/create', $this->battleNet->pullIntendedUrl());
        $this->assertNull($this->battleNet->pullIntendedUrl());
    }

    // ==================== helpers ====================

    private function authenticator(bool $expired = false): AccessTokenAuthenticator
    {
        $expiresAt = $expired ? now()->subMinute() : now()->addHour();

        return new AccessTokenAuthenticator('user-token', expiresAt: $expiresAt->toDateTimeImmutable());
    }

    private function apiException(int $status): ApiException
    {
        return new ApiException('GET', '/profile/user/wow', $status, $this->createStub(Response::class));
    }

    private function assertSessionError(BattleNetSessionError $expected, callable $callback): void
    {
        try {
            $callback();
        } catch (BattleNetSessionException $exception) {
            $this->assertSame($expected, $exception->error);

            return;
        }

        $this->fail('Expected a BattleNetSessionException.');
    }
}
