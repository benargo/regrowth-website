<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\Item;

use App\Http\Integrations\Blizzard\Data\Media\MediaData;
use App\Http\Integrations\Blizzard\Exceptions\ItemNotFoundException;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

#[Group('blizzard-integration')]
class GetItemMediaRequestTest extends BlizzardTestCase
{
    #[Test]
    public function it_casts_response_to_media_data(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetItemMediaRequest::class => MockResponse::make(body: [
                'id' => 19019,
                'assets' => [
                    ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/icons/56/inv_sword_39.jpg', 'file_data_id' => 132221],
                ],
            ], status: 200),
        ]);

        $dto = $this->makeConnector()
            ->send(new GetItemMediaRequest(19019))
            ->dto();

        $this->assertInstanceOf(MediaData::class, $dto);
        $this->assertSame(19019, $dto->id);
        $this->assertCount(1, $dto->assets);
        $this->assertSame('https://render.worldofwarcraft.com/icons/56/inv_sword_39.jpg', (string) $dto->assets[0]->value);
        $this->assertSame('icon', $dto->assets[0]->key);
        $this->assertSame(132221, $dto->assets[0]->fileDataId);
    }

    #[Test]
    public function it_resolves_correct_endpoint(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetItemMediaRequest::class => MockResponse::make(body: [
                'id' => 19019,
                'assets' => [],
            ], status: 200),
        ]);

        $this->makeConnector()->send(new GetItemMediaRequest(19019));

        Saloon::assertSent(fn (GetItemMediaRequest $r) => $r->resolveEndpoint() === '/data/wow/media/item/19019'
        );
    }

    #[Group('error-handling')]
    #[Test]
    public function it_throws_item_not_found_exception_on_404(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetItemMediaRequest::class => MockResponse::make(
                body: ['type' => 'BLZWEBAPI00000404', 'detail' => 'Not found'],
                status: 404,
            ),
        ]);

        $this->expectException(ItemNotFoundException::class);

        $this->makeConnector()->send(new GetItemMediaRequest(999999));
    }
}
