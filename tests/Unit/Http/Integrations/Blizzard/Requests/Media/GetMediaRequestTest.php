<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\Media;

use App\Http\Integrations\Blizzard\Data\Media\MediaData;
use App\Http\Integrations\Blizzard\Requests\Media\GetMediaRequest;
use App\Http\Integrations\Blizzard\Responses\GetMediaResponse;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

class GetMediaRequestTest extends BlizzardTestCase
{
    #[Test]
    public function it_returns_a_get_media_response(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetMediaRequest::class => MockResponse::make(body: [
                'id' => 19019,
                'assets' => [
                    ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/icons/56/inv_sword_39.jpg', 'file_data_id' => 132221],
                ],
            ], status: 200),
        ]);

        $response = $this->makeConnector()->send(new GetMediaRequest('item', 19019));

        $this->assertInstanceOf(GetMediaResponse::class, $response);

        $dto = $response->dto();
        $this->assertInstanceOf(MediaData::class, $dto);
        $this->assertSame(19019, $dto->id);
        $this->assertCount(1, $dto->assets);
        $this->assertSame('https://render.worldofwarcraft.com/icons/56/inv_sword_39.jpg', (string) $dto->assets[0]->value);
    }

    #[Test]
    public function it_resolves_correct_endpoint_with_tag_and_id(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetMediaRequest::class => MockResponse::make(body: [
                'id' => 19019,
                'assets' => [],
            ], status: 200),
        ]);

        $this->makeConnector()->send(new GetMediaRequest('item', 19019));

        Saloon::assertSent(fn (GetMediaRequest $r) => $r->resolveEndpoint() === '/data/wow/media/item/19019');
    }

    #[Test]
    public function it_throws_invalid_argument_exception_for_invalid_tag(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid tag "invalid-tag"/');

        new GetMediaRequest('invalid-tag', 1);
    }

    #[Test]
    public function it_throws_invalid_argument_exception_for_empty_tag(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GetMediaRequest('', 1);
    }
}
