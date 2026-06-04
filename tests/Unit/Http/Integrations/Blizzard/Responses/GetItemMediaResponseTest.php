<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Responses;

use App\Http\Integrations\Blizzard\Data\Media\MediaData;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Responses\GetItemMediaResponse;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

class GetItemMediaResponseTest extends BlizzardTestCase
{
    private function fakeGetItemMediaRequest(array $assets = []): GetItemMediaResponse
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetItemMediaRequest::class => MockResponse::make(body: [
                'id' => 19019,
                'assets' => $assets,
            ], status: 200),
        ]);

        /** @var GetItemMediaResponse $response */
        $response = $this->makeConnector()->send(new GetItemMediaRequest(19019));

        return $response;
    }

    #[Test]
    public function it_extends_saloon_response(): void
    {
        $response = $this->fakeGetItemMediaRequest();

        $this->assertInstanceOf(Response::class, $response);
    }

    #[Test]
    public function dto_returns_a_media_data_dto(): void
    {
        $response = $this->fakeGetItemMediaRequest([
            ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/icons/56/inv_sword_39.jpg', 'file_data_id' => 132221],
        ]);

        $data = $response->dto();

        $this->assertInstanceOf(MediaData::class, $data);
        $this->assertSame(19019, $data->id);
        $this->assertCount(1, $data->assets);
        $this->assertSame('https://render.worldofwarcraft.com/icons/56/inv_sword_39.jpg', $data->assets[0]->value);
    }

    #[Test]
    public function dto_is_memoized_and_returns_the_same_instance(): void
    {
        $response = $this->fakeGetItemMediaRequest([
            ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/icons/56/inv_sword_39.jpg', 'file_data_id' => 132221],
        ]);

        $first = $response->dto();
        $second = $response->dto();

        $this->assertSame($first, $second);
    }
}
