<?php

namespace Tests\Unit\Http\Integrations\Blizzard;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Exceptions\InvalidRaceException;
use App\Http\Integrations\Blizzard\GameVersion;
use App\Http\Integrations\Blizzard\Region;
use App\Services\Blizzard\Exceptions\BlizzardApiException;
use App\Services\Blizzard\Exceptions\BlizzardRequestException;
use App\Services\Blizzard\Exceptions\CharacterNotFoundException;
use App\Services\Blizzard\Exceptions\ItemNotFoundException;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Enums\Method;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Tests\TestCase;

class BlizzardConnectorTest extends TestCase
{
    private function makeConnector(?Region $region = null, GameVersion $gameVersion = GameVersion::Anniversary): BlizzardConnector
    {
        $region ??= Region::EU;

        return new BlizzardConnector(
            clientId: 'test_id',
            clientSecret: 'test_secret',
            region: $region,
            locale: $region->defaultLocale(),
            gameVersion: $gameVersion,
        );
    }

    private function tokenMock(): MockResponse
    {
        return MockResponse::make([
            'access_token' => 'test_token',
            'token_type' => 'bearer',
            'expires_in' => 3600,
        ]);
    }

    // ==================== resolveBaseUrl ====================

    #[Test]
    public function resolves_base_url_from_region(): void
    {
        $this->assertSame('https://eu.api.blizzard.com', $this->makeConnector(Region::EU)->resolveBaseUrl());
        $this->assertSame('https://us.api.blizzard.com', $this->makeConnector(Region::US)->resolveBaseUrl());
        $this->assertSame('https://kr.api.blizzard.com', $this->makeConnector(Region::KR)->resolveBaseUrl());
        $this->assertSame('https://tw.api.blizzard.com', $this->makeConnector(Region::TW)->resolveBaseUrl());
    }

    // ==================== namespace lookup ====================

    #[Test]
    public function namespace_returns_derived_value_for_anniversary(): void
    {
        $connector = $this->makeConnector(Region::EU, GameVersion::Anniversary);

        $this->assertSame('profile-classicann-eu', $connector->namespace('profile'));
        $this->assertSame('static-classicann-eu', $connector->namespace('static'));
        $this->assertSame('dynamic-classicann-eu', $connector->namespace('dynamic'));
    }

    #[Test]
    public function namespace_returns_derived_value_for_retail(): void
    {
        $connector = $this->makeConnector(Region::EU, GameVersion::Retail);

        $this->assertSame('profile-eu', $connector->namespace('profile'));
        $this->assertSame('static-eu', $connector->namespace('static'));
        $this->assertSame('dynamic-eu', $connector->namespace('dynamic'));
    }

    #[Test]
    public function namespace_throws_for_unknown_kind(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Blizzard namespace kind: bogus');

        $this->makeConnector()->namespace('bogus');
    }

    // ==================== locale validation ====================

    #[Test]
    public function constructor_rejects_a_locale_unsupported_by_the_region(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Locale "ko_KR" is not supported for region "eu"');

        new BlizzardConnector(
            clientId: 'test_id',
            clientSecret: 'test_secret',
            gameVersion: GameVersion::Anniversary,
            region: Region::EU,
            locale: 'ko_KR',
        );
    }

    // ==================== OAuth + token caching ====================

    #[Test]
    public function authenticates_outgoing_requests_with_cached_oauth_token(): void
    {
        $mock = Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            'eu.api.blizzard.com/data/wow/item/19019' => MockResponse::make(['id' => 19019, 'name' => 'Thunderfury']),
        ]);

        $connector = $this->makeConnector();
        $response = $connector->send(new TestItemRequest(19019));

        $this->assertSame(200, $response->status());

        $lastPending = $mock->getLastPendingRequest();
        $this->assertNotNull($lastPending);
        $this->assertSame('Bearer test_token', $lastPending->headers()->get('Authorization'));
    }

    #[Test]
    public function reuses_cached_access_token_on_subsequent_requests(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            'eu.api.blizzard.com/*' => MockResponse::make(['ok' => true]),
        ]);

        $connector = $this->makeConnector();
        $connector->send(new TestItemRequest(1));
        $connector->send(new TestItemRequest(2));

        // The token endpoint should only be hit once.
        Saloon::assertSentCount(3); // token + 2 api requests

        // Verify the cache holds the token under the expected key & tags.
        $cached = Cache::tags(['blizzard', 'api-auth'])->get('blizzard:access_token:v2:eu');
        $this->assertIsArray($cached);
        $this->assertSame('test_token', $cached['token']);
    }

    // ==================== Exception mapping ====================

    #[Test]
    public function throws_character_not_found_on_blizzard_404_for_character_endpoint(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            'eu.api.blizzard.com/profile/wow/character/*' => MockResponse::make(
                ['type' => 'BLZWEBAPI00000404', 'detail' => 'Not found'],
                404,
            ),
        ]);

        $this->expectException(CharacterNotFoundException::class);

        $this->makeConnector()->send(new TestCharacterRequest('thunderstrike', 'ghost'));
    }

    #[Test]
    public function throws_invalid_race_exception_on_blizzard_404_for_race_endpoint(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            'eu.api.blizzard.com/data/wow/playable-race/*' => MockResponse::make(
                ['type' => 'BLZWEBAPI00000404'],
                404,
            ),
        ]);

        $this->expectException(InvalidRaceException::class);

        $this->makeConnector()->send(new TestRaceRequest(999));
    }

    #[Test]
    public function throws_blizzard_api_exception_on_blizzard_404_for_class_endpoint(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            'eu.api.blizzard.com/data/wow/playable-class/*' => MockResponse::make(
                ['type' => 'BLZWEBAPI00000404'],
                404,
            ),
        ]);

        $this->expectException(BlizzardApiException::class);

        $this->makeConnector()->send(new TestClassRequest(999));
    }

    #[Test]
    public function throws_item_not_found_on_blizzard_404_for_item_endpoint(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            'eu.api.blizzard.com/data/wow/item/*' => MockResponse::make(
                ['type' => 'BLZWEBAPI00000404'],
                404,
            ),
        ]);

        $this->expectException(ItemNotFoundException::class);

        $this->makeConnector()->send(new TestItemRequest(999999));
    }

    #[Test]
    public function throws_rate_limited_on_429_with_retry_after(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            'eu.api.blizzard.com/data/wow/item/*' => MockResponse::make(
                ['type' => 'BLZWEBAPI00000429'],
                429,
                ['Retry-After' => '7'],
            ),
        ]);

        $this->expectException(RateLimitReachedException::class);

        $this->makeConnector()->send(new TestItemRequest(1));
    }

    #[Test]
    public function throws_blizzard_api_exception_for_other_failures(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            'eu.api.blizzard.com/data/wow/item/*' => MockResponse::make(
                ['type' => 'BLZWEBAPI00500000'],
                500,
            ),
        ]);

        try {
            $this->makeConnector()->send(new TestItemRequest(1));
            $this->fail('Expected BlizzardApiException');
        } catch (BlizzardRequestException $e) {
            $this->assertInstanceOf(BlizzardApiException::class, $e);
            $this->assertInstanceOf(ClientException::class, $e);
            $this->assertNotInstanceOf(ItemNotFoundException::class, $e);
            $this->assertSame(500, $e->blizzardStatus);
            $this->assertSame('/data/wow/item/1', $e->endpoint);
            $this->assertSame('GET', $e->method);
            $this->assertSame('BLZWEBAPI00500000', $e->blizzardCode);
        }
    }
}

// Lightweight per-endpoint requests used by the connector tests. Real request
// classes for the API land in Phase 2.

class TestItemRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(public readonly int $itemId) {}

    public function resolveEndpoint(): string
    {
        return "/data/wow/item/{$this->itemId}";
    }
}

class TestCharacterRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(public readonly string $realm, public readonly string $name) {}

    public function resolveEndpoint(): string
    {
        return "/profile/wow/character/{$this->realm}/{$this->name}";
    }
}

class TestRaceRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(public readonly int $id) {}

    public function resolveEndpoint(): string
    {
        return "/data/wow/playable-race/{$this->id}";
    }
}

class TestClassRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(public readonly int $id) {}

    public function resolveEndpoint(): string
    {
        return "/data/wow/playable-class/{$this->id}";
    }
}
