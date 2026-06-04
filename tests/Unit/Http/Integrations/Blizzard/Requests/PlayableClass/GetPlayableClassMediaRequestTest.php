<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\PlayableClass;

use App\Http\Integrations\Blizzard\Data\PlayableClass\PlayableClassMediaData;
use App\Http\Integrations\Blizzard\Exceptions\InvalidClassException;
use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassMediaRequest;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Spatie\LaravelData\Optional;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

class GetPlayableClassMediaRequestTest extends BlizzardTestCase
{
    #[Test]
    public function it_casts_response_to_playable_class_media_data(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetPlayableClassMediaRequest::class => MockResponse::make(body: [
                'id' => 7,
                'assets' => [
                    [
                        'key' => 'icon',
                        'value' => 'https://render.worldofwarcraft.com/eu/icons/56/shaman.jpg',
                        'file_data_id' => 642015,
                    ],
                ],
            ], status: 200),
        ]);

        $dto = $this->makeConnector()
            ->send(new GetPlayableClassMediaRequest(7))
            ->dto();

        $this->assertInstanceOf(PlayableClassMediaData::class, $dto);
        $this->assertSame(7, $dto->id);
        $this->assertCount(1, $dto->assets);
        $this->assertSame('https://render.worldofwarcraft.com/eu/icons/56/shaman.jpg', $dto->assets[0]->value);
        $this->assertSame('icon', $dto->assets[0]->key);
        $this->assertSame(642015, $dto->assets[0]->fileDataId);
    }

    #[Test]
    public function it_treats_missing_asset_key_and_file_data_id_as_optional(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetPlayableClassMediaRequest::class => MockResponse::make(body: [
                'id' => 1,
                'assets' => [
                    ['value' => 'https://render.worldofwarcraft.com/eu/icons/56/warrior.jpg'],
                ],
            ], status: 200),
        ]);

        $dto = $this->makeConnector()
            ->send(new GetPlayableClassMediaRequest(1))
            ->dto();

        $this->assertInstanceOf(Optional::class, $dto->assets[0]->key);
        $this->assertInstanceOf(Optional::class, $dto->assets[0]->fileDataId);
    }

    #[Test]
    public function it_resolves_correct_endpoint(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetPlayableClassMediaRequest::class => MockResponse::make(body: [
                'id' => 3,
                'assets' => [],
            ], status: 200),
        ]);

        $this->makeConnector()->send(new GetPlayableClassMediaRequest(3));

        Saloon::assertSent(fn (GetPlayableClassMediaRequest $r) => $r->resolveEndpoint() === '/data/wow/media/playable-class/3'
        );
    }

    #[Test]
    public function it_throws_invalid_class_exception_on_404(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetPlayableClassMediaRequest::class => MockResponse::make(
                body: ['type' => 'BLZWEBAPI00000404', 'detail' => 'Not found'],
                status: 404,
            ),
        ]);

        $this->expectException(InvalidClassException::class);

        $this->makeConnector()->send(new GetPlayableClassMediaRequest(9999));
    }
}
