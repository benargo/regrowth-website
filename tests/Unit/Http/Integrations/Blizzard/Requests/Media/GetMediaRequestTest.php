<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\Media;

use App\Http\Integrations\Blizzard\Data\Media\MediaData;
use App\Http\Integrations\Blizzard\Requests\Media\GetMediaRequest;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

class GetMediaRequestTest extends BlizzardTestCase
{
    #[Test]
    public function it_casts_response_to_media_data_for_item_tag(): void
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

        $dto = $this->makeConnector()
            ->send(new GetMediaRequest('item', 19019))
            ->dto();

        $this->assertInstanceOf(MediaData::class, $dto);
        $this->assertSame(19019, $dto->id);
        $this->assertCount(1, $dto->assets);
        $this->assertSame('https://render.worldofwarcraft.com/icons/56/inv_sword_39.jpg', $dto->assets[0]->value);
    }

    #[Test]
    public function it_casts_response_to_media_data_for_spell_tag(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetMediaRequest::class => MockResponse::make(body: [
                'id' => 5,
                'assets' => [
                    ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/icons/56/spell_fire.jpg', 'file_data_id' => 555],
                ],
            ], status: 200),
        ]);

        $dto = $this->makeConnector()
            ->send(new GetMediaRequest('spell', 5))
            ->dto();

        $this->assertInstanceOf(MediaData::class, $dto);
        $this->assertSame(5, $dto->id);
    }

    #[Test]
    public function it_casts_response_to_media_data_for_playable_class_tag(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetMediaRequest::class => MockResponse::make(body: [
                'id' => 1,
                'assets' => [
                    ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/icons/56/class_warrior.jpg', 'file_data_id' => 999],
                ],
            ], status: 200),
        ]);

        $dto = $this->makeConnector()
            ->send(new GetMediaRequest('playable-class', 1))
            ->dto();

        $this->assertInstanceOf(MediaData::class, $dto);
        $this->assertSame(1, $dto->id);
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

        Saloon::assertSent(fn (GetMediaRequest $r) => $r->resolveEndpoint() === '/data/wow/media/item/19019'
        );
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
