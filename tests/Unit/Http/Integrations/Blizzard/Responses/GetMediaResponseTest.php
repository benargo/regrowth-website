<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Responses;

use App\Http\Integrations\Blizzard\Data\Media\MediaData;
use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Media\GetMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use App\Http\Integrations\Blizzard\Responses\GetMediaResponse;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

#[Group('blizzard-integration')]
class GetMediaResponseTest extends BlizzardTestCase
{
    private function fakeGetMediaRequest(array $assets = []): GetMediaResponse
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetMediaRequest::class => MockResponse::make(body: [
                'id' => 19019,
                'assets' => $assets,
            ], status: 200),
        ]);

        /** @var GetMediaResponse $response */
        $response = $this->makeConnector()->send(new GetMediaRequest('item', 19019));

        return $response;
    }

    #[Test]
    public function it_extends_saloon_response(): void
    {
        $response = $this->fakeGetMediaRequest();

        $this->assertInstanceOf(Response::class, $response);
    }

    #[Test]
    public function dto_returns_a_media_data_dto(): void
    {
        $response = $this->fakeGetMediaRequest([
            ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/icons/56/inv_sword_39.jpg', 'file_data_id' => 132221],
        ]);

        $data = $response->dto();

        $this->assertInstanceOf(MediaData::class, $data);
        $this->assertSame(19019, $data->id);
        $this->assertCount(1, $data->assets);
        $this->assertSame('https://render.worldofwarcraft.com/icons/56/inv_sword_39.jpg', (string) $data->assets[0]->value);
    }

    #[Test]
    public function mirror_sends_a_fetch_asset_request_per_asset_and_writes_to_disk(): void
    {
        Storage::fake('public');

        $assetUrl = 'https://render.worldofwarcraft.com/eu/icons/56/inv_sword_39.jpg';

        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetMediaRequest::class => MockResponse::make(body: [
                'id' => 19019,
                'assets' => [
                    ['key' => 'icon', 'value' => $assetUrl, 'file_data_id' => 132221],
                ],
            ], status: 200),
            FetchIconRequest::class => MockResponse::make(
                body: 'fake-image-content',
                status: 200,
                headers: ['Content-Type' => 'image/jpeg'],
            ),
        ]);

        $renderConnector = new RenderConnector(Region::EU, Storage::disk('public'));

        $response = $this->makeConnector()->send(new GetMediaRequest('item', 19019));

        /** @var GetMediaResponse $response */
        $response->mirror($renderConnector);

        $asset = $response->dto()->assets[0];
        $this->assertNotNull($asset->mirroredPath());
        Storage::disk('public')->assertExists($asset->mirroredPath());
    }

    #[Test]
    public function mirror_returns_self_for_chaining(): void
    {
        Storage::fake('public');

        $assetUrl = 'https://render.worldofwarcraft.com/eu/icons/56/inv_sword_39.jpg';

        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetMediaRequest::class => MockResponse::make(body: [
                'id' => 19019,
                'assets' => [
                    ['key' => 'icon', 'value' => $assetUrl, 'file_data_id' => 132221],
                ],
            ], status: 200),
            FetchIconRequest::class => MockResponse::make(
                body: 'fake-image-content',
                status: 200,
                headers: ['Content-Type' => 'image/jpeg'],
            ),
        ]);

        $renderConnector = new RenderConnector(Region::EU, Storage::disk('public'));

        /** @var GetMediaResponse $response */
        $response = $this->makeConnector()->send(new GetMediaRequest('item', 19019));

        $returned = $response->mirror($renderConnector);

        $this->assertSame($response, $returned);
    }
}
