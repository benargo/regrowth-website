<?php

namespace Tests\Unit\Http\Integrations\Blizzard;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\GameVersion;
use App\Http\Integrations\Blizzard\Region;
use Saloon\Http\Faking\MockResponse;
use Tests\TestCase;

abstract class BlizzardTestCase extends TestCase
{
    protected function makeConnector(
        ?Region $region = null,
        GameVersion $gameVersion = GameVersion::Anniversary,
    ): BlizzardConnector {
        $region ??= Region::EU;

        return new BlizzardConnector(
            clientId: 'test_id',
            clientSecret: 'test_secret',
            region: $region,
            locale: $region->defaultLocale(),
            gameVersion: $gameVersion,
        );
    }

    /**
     * Mock response for the OAuth client-credentials token endpoint.
     *
     * Every test that constructs a connector and sends a request MUST include
     * this in its Saloon::fake() map at the `{region}.battle.net/oauth/token`
     * URL key, otherwise the connector's boot() will hit the live Blizzard
     * token endpoint.
     */
    protected function tokenMock(): MockResponse
    {
        return MockResponse::make([
            'access_token' => 'test_token',
            'token_type' => 'bearer',
            'expires_in' => 3600,
        ]);
    }
}
