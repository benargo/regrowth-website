<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Responses;

use App\Http\Integrations\Blizzard\Data\PlayableClass\PlayableClassMediaData;
use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassMediaRequest;
use App\Http\Integrations\Blizzard\Responses\GetPlayableClassMediaResponse;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

class GetPlayableClassMediaResponseTest extends BlizzardTestCase
{
    private function fakeGetPlayableClassMediaRequest(array $assets = []): GetPlayableClassMediaResponse
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetPlayableClassMediaRequest::class => MockResponse::make(body: [
                'id' => 7,
                'assets' => $assets,
            ], status: 200),
        ]);

        /** @var GetPlayableClassMediaResponse $response */
        $response = $this->makeConnector()->send(new GetPlayableClassMediaRequest(7));

        return $response;
    }

    #[Test]
    public function it_extends_saloon_response(): void
    {
        $response = $this->fakeGetPlayableClassMediaRequest();

        $this->assertInstanceOf(Response::class, $response);
    }

    #[Test]
    public function dto_returns_a_playable_class_media_data_dto(): void
    {
        $response = $this->fakeGetPlayableClassMediaRequest([
            ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/icons/56/classicon_shaman.jpg', 'file_data_id' => 626001],
        ]);

        $data = $response->dto();

        $this->assertInstanceOf(PlayableClassMediaData::class, $data);
        $this->assertSame(7, $data->id);
        $this->assertCount(1, $data->assets);
        $this->assertSame('https://render.worldofwarcraft.com/icons/56/classicon_shaman.jpg', $data->assets[0]->value);
    }

    #[Test]
    public function dto_is_memoized_and_returns_the_same_instance(): void
    {
        $response = $this->fakeGetPlayableClassMediaRequest([
            ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/icons/56/classicon_shaman.jpg', 'file_data_id' => 626001],
        ]);

        $first = $response->dto();
        $second = $response->dto();

        $this->assertSame($first, $second);
    }
}
