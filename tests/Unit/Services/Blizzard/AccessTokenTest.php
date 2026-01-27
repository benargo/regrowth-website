<?php

namespace Tests\Unit\Services\Blizzard;

use App\Exceptions\ExpiredTokenException;
use App\Services\Blizzard\AccessToken;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class AccessTokenTest extends TestCase
{
    public function test_constructor_sets_properties(): void
    {
        $createdAt = Carbon::parse('2024-01-01 12:00:00');
        $token = new AccessToken(
            token: 'test_token',
            tokenType: 'Bearer',
            expiresIn: 3600,
            createdAt: $createdAt,
        );

        $this->assertEquals('test_token', $token->token);
        $this->assertEquals('Bearer', $token->tokenType);
        $this->assertEquals(3600, $token->expiresIn);
        $this->assertTrue($createdAt->equalTo($token->createdAt));
    }

    public function test_expires_at_is_calculated_correctly(): void
    {
        $createdAt = Carbon::parse('2024-01-01 12:00:00');
        $token = new AccessToken(
            token: 'test_token',
            tokenType: 'Bearer',
            expiresIn: 3600,
            createdAt: $createdAt,
        );

        $expectedExpiry = Carbon::parse('2024-01-01 13:00:00');
        $this->assertTrue($expectedExpiry->equalTo($token->expiresAt));
    }

    public function test_from_response_creates_token(): void
    {
        $response = [
            'access_token' => 'response_token',
            'token_type' => 'Bearer',
            'expires_in' => 7200,
        ];

        $token = AccessToken::fromResponse($response);

        $this->assertEquals('response_token', $token->token);
        $this->assertEquals('Bearer', $token->tokenType);
        $this->assertEquals(7200, $token->expiresIn);
    }

    public function test_is_expired_returns_false_for_valid_token(): void
    {
        $token = new AccessToken(
            token: 'test_token',
            tokenType: 'Bearer',
            expiresIn: 3600,
            createdAt: Carbon::now(),
        );

        $this->assertFalse($token->isExpired());
    }

    public function test_is_expired_returns_true_for_expired_token(): void
    {
        $token = new AccessToken(
            token: 'test_token',
            tokenType: 'Bearer',
            expiresIn: 3600,
            createdAt: Carbon::now()->subHours(2),
        );

        $this->assertTrue($token->isExpired());
    }

    public function test_get_token_returns_token_when_valid(): void
    {
        $token = new AccessToken(
            token: 'valid_token',
            tokenType: 'Bearer',
            expiresIn: 3600,
            createdAt: Carbon::now(),
        );

        $this->assertEquals('valid_token', $token->getToken());
    }

    public function test_get_token_throws_exception_when_expired(): void
    {
        $token = new AccessToken(
            token: 'expired_token',
            tokenType: 'Bearer',
            expiresIn: 3600,
            createdAt: Carbon::now()->subHours(2),
        );

        $this->expectException(ExpiredTokenException::class);
        $this->expectExceptionMessage('Token has expired');

        $token->getToken();
    }
}
