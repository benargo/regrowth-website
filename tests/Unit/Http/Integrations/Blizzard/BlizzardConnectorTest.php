<?php

namespace Tests\Unit\Http\Integrations\Blizzard;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Exceptions\InvalidClassException;
use App\Http\Integrations\Blizzard\Exceptions\InvalidRaceException;
use App\Http\Integrations\Blizzard\Exceptions\ItemNotFoundException;
use App\Http\Integrations\Blizzard\GameVersion;
use App\Http\Integrations\Blizzard\Middleware\EagerlyMirrorAssets;
use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceRequest;
use App\Services\Blizzard\Exceptions\BlizzardApiException;
use App\Services\Blizzard\Exceptions\BlizzardRequestException;
use App\Services\Blizzard\Exceptions\CharacterNotFoundException;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Tests\TestCase;

class BlizzardConnectorTest extends TestCase
{
    private function makeConnector(
        ?Region $region = null,
        GameVersion $gameVersion = GameVersion::Anniversary,
        string $defaultRealmSlug = 'thunderstrike',
        string $defaultGuildSlug = 'regrowth',
    ): BlizzardConnector {
        $region ??= Region::EU;

        return new BlizzardConnector(
            clientId: 'test_id',
            clientSecret: 'test_secret',
            region: $region,
            locale: $region->defaultLocale(),
            gameVersion: $gameVersion,
            defaultRealmSlug: $defaultRealmSlug,
            defaultGuildSlug: $defaultGuildSlug,
            eagerlyMirrorAssets: $this->createStub(EagerlyMirrorAssets::class),
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
            defaultRealmSlug: 'thunderstrike',
            defaultGuildSlug: 'regrowth',
            eagerlyMirrorAssets: $this->createStub(EagerlyMirrorAssets::class),
        );
    }

    // ==================== default slugs ====================

    #[Test]
    public function it_exposes_the_configured_default_realm_slug(): void
    {
        $connector = $this->makeConnector(defaultRealmSlug: 'thunderstrike', defaultGuildSlug: 'regrowth');

        $this->assertSame('thunderstrike', $connector->defaultRealmSlug());
    }

    #[Test]
    public function it_exposes_the_configured_default_guild_slug(): void
    {
        $connector = $this->makeConnector(defaultRealmSlug: 'thunderstrike', defaultGuildSlug: 'regrowth');

        $this->assertSame('regrowth', $connector->defaultGuildSlug());
    }

    // ==================== OAuth + token caching ====================

    #[Test]
    public function authenticates_outgoing_requests_with_cached_oauth_token(): void
    {
        $mock = Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            'eu.api.blizzard.com/data/wow/item/19019' => MockResponse::make(['id' => 19019, 'name' => 'Thunderfury']),
        ]);

        $request = Mockery::mock(GetItemRequest::class)->makePartial()->allows('resolveEndpoint')->andReturn('/data/wow/item/19019')->getMock();

        $connector = $this->makeConnector();
        $response = $connector->send($request);

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
        $connector->send(Mockery::mock(GetItemRequest::class)->makePartial()->allows('resolveEndpoint')->andReturn('/data/wow/item/1')->getMock());
        $connector->send(Mockery::mock(GetItemRequest::class)->makePartial()->allows('resolveEndpoint')->andReturn('/data/wow/item/2')->getMock());

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

        $request = Mockery::mock(GetCharacterProfileRequest::class)->makePartial()->allows('resolveEndpoint')->andReturn('/profile/wow/character/thunderstrike/ghost')->getMock();

        $this->makeConnector()->send($request);
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

        $request = Mockery::mock(GetPlayableRaceRequest::class)->makePartial()->allows('resolveEndpoint')->andReturn('/data/wow/playable-race/999')->getMock();

        $this->makeConnector()->send($request);
    }

    #[Test]
    public function throws_invalid_class_exception_on_blizzard_404_for_class_endpoint(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            'eu.api.blizzard.com/data/wow/playable-class/*' => MockResponse::make(
                ['type' => 'BLZWEBAPI00000404'],
                404,
            ),
        ]);

        $this->expectException(InvalidClassException::class);

        $request = Mockery::mock(GetPlayableClassRequest::class)->makePartial()->allows('resolveEndpoint')->andReturn('/data/wow/playable-class/999')->getMock();

        $this->makeConnector()->send($request);
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

        $request = Mockery::mock(GetItemRequest::class)->makePartial()->allows('resolveEndpoint')->andReturn('/data/wow/item/999999')->getMock();

        $this->makeConnector()->send($request);
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

        $request = Mockery::mock(GetItemRequest::class)->makePartial()->allows('resolveEndpoint')->andReturn('/data/wow/item/1')->getMock();

        $this->makeConnector()->send($request);
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

        $request = Mockery::mock(GetItemRequest::class)->makePartial()->allows('resolveEndpoint')->andReturn('/data/wow/item/1')->getMock();

        try {
            $this->makeConnector()->send($request);
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
