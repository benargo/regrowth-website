<?php

namespace Tests\Unit\Services\Blizzard;

use App\Services\Blizzard\Client;
use App\Services\Blizzard\Region;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class ClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.blizzard.client_id' => 'test_client_id',
            'services.blizzard.client_secret' => 'test_client_secret',
            'services.blizzard.region' => 'eu',
            'services.blizzard.locale' => 'en_GB',
            'services.blizzard.namespace' => 'static-classic1x-eu',
        ]);
    }

    public function test_constructor_sets_region_and_locale_from_config(): void
    {
        $client = new Client('client_id', 'client_secret', namespace: 'static-classic-eu');

        $this->assertEquals(Region::EU, $client->getRegion());
        $this->assertEquals('en_GB', $client->getLocale());
        $this->assertEquals('static-classic-eu', $client->getNamespace());
    }

    public function test_constructor_accepts_explicit_region_and_locale(): void
    {
        $client = new Client(
            clientId: 'client_id',
            clientSecret: 'client_secret',
            region: Region::US,
            locale: 'en_US',
            namespace: 'dynamic-classic1x-us',
        );

        $this->assertEquals(Region::US, $client->getRegion());
        $this->assertEquals('en_US', $client->getLocale());
        $this->assertEquals('dynamic-classic1x-us', $client->getNamespace());
    }

    public function test_constructor_throws_exception_for_invalid_locale(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Locale "invalid_locale" is not supported for region "eu"');

        new Client(
            clientId: 'client_id',
            clientSecret: 'client_secret',
            region: Region::EU,
            locale: 'invalid_locale',
        );
    }

    public function test_from_config_creates_client(): void
    {
        $client = Client::fromConfig();

        $this->assertEquals(Region::EU, $client->getRegion());
        $this->assertEquals('en_GB', $client->getLocale());
    }

    public function test_set_region_updates_region(): void
    {
        $client = new Client('client_id', 'client_secret');

        $result = $client->setRegion(Region::US);

        $this->assertSame($client, $result);
        $this->assertEquals(Region::US, $client->getRegion());
    }

    public function test_set_region_resets_locale_if_incompatible(): void
    {
        $client = new Client(
            clientId: 'client_id',
            clientSecret: 'client_secret',
            region: Region::EU,
            locale: 'de_DE',
        );

        $client->setRegion(Region::US);

        $this->assertEquals('en_US', $client->getLocale());
    }

    public function test_set_locale_updates_locale(): void
    {
        $client = new Client('client_id', 'client_secret');

        $result = $client->setLocale('fr_FR');

        $this->assertSame($client, $result);
        $this->assertEquals('fr_FR', $client->getLocale());
    }

    public function test_set_locale_throws_exception_for_invalid_locale(): void
    {
        $client = new Client('client_id', 'client_secret');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Locale "ko_KR" is not supported for region "eu"');

        $client->setLocale('ko_KR');
    }

    public function test_with_namespace_updates_namespace(): void
    {
        $client = new Client('client_id', 'client_secret');

        $result = $client->withNamespace('dynamic-classic1x-eu');

        $this->assertSame($client, $result);
        $this->assertEquals('dynamic-classic1x-eu', $client->getNamespace());
    }

    public function test_http_returns_pending_request(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $request = $client->http();

        $this->assertInstanceOf(PendingRequest::class, $request);
    }

    public function test_get_access_token_requests_new_token(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'new_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $token = $client->getAccessToken();

        $this->assertEquals('new_token', $token);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://eu.battle.net/oauth/token'
                && $request->hasHeader('Authorization')
                && $request['grant_type'] === 'client_credentials';
        });
    }

    public function test_get_access_token_caches_token_per_region(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'eu_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'us.battle.net/oauth/token' => Http::response([
                'access_token' => 'us_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
        ]);

        $client = new Client(
            clientId: 'client_id',
            clientSecret: 'client_secret',
            region: Region::EU,
            locale: 'en_GB',
        );

        // First call - should request token
        $euToken = $client->getAccessToken();
        $this->assertEquals('eu_token', $euToken);

        // Second call - should use cached token
        $cachedToken = $client->getAccessToken();
        $this->assertEquals('eu_token', $cachedToken);

        // Change region and request token
        $client->setRegion(Region::US);
        $usToken = $client->getAccessToken();
        $this->assertEquals('us_token', $usToken);

        // Switch back to EU - should still have cached token
        $client->setRegion(Region::EU);
        $cachedEuToken = $client->getAccessToken();
        $this->assertEquals('eu_token', $cachedEuToken);
    }

    public function test_get_access_token_refreshes_when_cache_is_cleared(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::sequence()
                ->push([
                    'access_token' => 'first_token',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ])
                ->push([
                    'access_token' => 'second_token',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ]),
        ]);

        $client = new Client('client_id', 'client_secret');

        // Get first token
        $firstToken = $client->getAccessToken();
        $this->assertEquals('first_token', $firstToken);

        // Clear the cache to simulate expiry
        Cache::forget('blizzard_access_token_eu');

        // Should request a new token because the cache was cleared
        $newToken = $client->getAccessToken();
        $this->assertEquals('second_token', $newToken);
    }

    public function test_get_access_token_stores_token_in_cache(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'cached_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $client->getAccessToken();

        $this->assertEquals('cached_token', Cache::get('blizzard_access_token_eu'));
    }

    public function test_http_includes_namespace_header_when_set(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['data' => 'test']),
        ]);

        $client = new Client('client_id', 'client_secret');
        $client->http()->get('/data/wow/item/19019');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return $request->hasHeader('Battlenet-Namespace', 'static-classic1x-eu');
            }

            return true;
        });
    }

    public function test_http_does_not_include_namespace_header_when_null(): void
    {
        config(['services.blizzard.namespace' => null]);

        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['data' => 'test']),
        ]);

        $client = new Client('client_id', 'client_secret');
        $client->http()->get('/data/wow/item/19019');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return ! $request->hasHeader('Battlenet-Namespace');
            }

            return true;
        });
    }

    public function test_with_namespace_allows_fluent_override(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['data' => 'test']),
        ]);

        $client = new Client('client_id', 'client_secret');
        $client->withNamespace('dynamic-classic1x-eu')->http()->get('/data/wow/connected-realm/index');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return $request->hasHeader('Battlenet-Namespace', 'dynamic-classic1x-eu');
            }

            return true;
        });
    }
}
