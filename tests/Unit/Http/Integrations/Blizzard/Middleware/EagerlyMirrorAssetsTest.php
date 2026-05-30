<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Middleware;

use App\Http\Integrations\Blizzard\Attributes\EagerlyMirrorsAssets;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

class EagerlyMirrorAssetsTest extends BlizzardTestCase
{
    #[Test]
    public function get_item_media_request_carries_the_eagerly_mirrors_assets_attribute(): void
    {
        $attributes = (new \ReflectionClass(GetItemMediaRequest::class))
            ->getAttributes(EagerlyMirrorsAssets::class);

        $this->assertNotEmpty($attributes);
    }

    #[Test]
    public function it_warms_every_asset_on_a_get_item_media_response(): void
    {
        Storage::fake('public');

        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetItemMediaRequest::class => MockResponse::make(body: [
                'id' => 19019,
                'assets' => [
                    [
                        'key' => 'icon',
                        'value' => 'https://render.worldofwarcraft.com/eu/icons/56/foo.jpg',
                        'file_data_id' => 12345,
                    ],
                    [
                        'key' => 'icon-large',
                        'value' => 'https://render.worldofwarcraft.com/eu/icons/256/foo.jpg',
                        'file_data_id' => 12346,
                    ],
                ],
            ], status: 200),
            FetchAssetRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $this->makeConnector()->send(new GetItemMediaRequest(19019));

        Storage::disk('public')->assertExists('blizzard-cdn/icons/56/foo.jpg');
        Storage::disk('public')->assertExists('blizzard-cdn/icons/256/foo.jpg');
    }
}
