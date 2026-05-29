<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\Item;

use App\Http\Integrations\Blizzard\Data\Item\ItemData;
use App\Http\Integrations\Blizzard\Exceptions\ItemNotFoundException;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

class GetItemRequestTest extends BlizzardTestCase
{
    #[Test]
    public function it_casts_response_to_item_data(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetItemRequest::class => MockResponse::make(body: [
                'id' => 19019,
                'name' => 'Thunderfury, Blessed Blade of the Windseeker',
                'quality' => ['type' => 'LEGENDARY', 'name' => 'Legendary'],
                'level' => 80,
                'required_level' => 60,
                'media' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/media/item/19019'], 'id' => 19019],
                'item_class' => ['key' => ['href' => 'ic'], 'name' => 'Weapon', 'id' => 2],
                'item_subclass' => ['key' => ['href' => 'isc'], 'name' => 'One-Handed Sword', 'id' => 7],
                'inventory_type' => ['type' => 'WEAPON', 'name' => 'One-Hand'],
                'purchase_price' => 0,
                'sell_price' => 0,
                'max_count' => 1,
                'is_equippable' => true,
                'is_stackable' => false,
            ], status: 200),
        ]);

        $dto = $this->makeConnector()
            ->send(new GetItemRequest(19019))
            ->dto();

        $this->assertInstanceOf(ItemData::class, $dto);
        $this->assertSame(19019, $dto->id);
        $this->assertSame('Thunderfury, Blessed Blade of the Windseeker', $dto->name);
        $this->assertSame('LEGENDARY', $dto->quality->type);
        $this->assertSame('Legendary', $dto->quality->name);
        $this->assertSame(80, $dto->level);
        $this->assertSame(60, $dto->requiredLevel);
        $this->assertSame(19019, $dto->media->id);
        $this->assertSame('Weapon', $dto->itemClass->name);
        $this->assertSame('One-Handed Sword', $dto->itemSubclass->name);
        $this->assertSame('WEAPON', $dto->inventoryType->type);
        $this->assertSame(1, $dto->maxCount);
        $this->assertTrue($dto->isEquippable);
        $this->assertFalse($dto->isStackable);
    }

    #[Test]
    public function it_resolves_correct_endpoint(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetItemRequest::class => MockResponse::make(body: [
                'id' => 1, 'name' => 'Test Item',
                'quality' => ['type' => 'COMMON', 'name' => 'Common'],
                'level' => 1, 'required_level' => 1,
                'media' => ['key' => ['href' => 'm'], 'id' => 1],
                'item_class' => ['key' => ['href' => 'ic'], 'name' => 'Miscellaneous', 'id' => 15],
                'item_subclass' => ['key' => ['href' => 'isc'], 'name' => 'Junk', 'id' => 0],
                'inventory_type' => ['type' => 'NON_EQUIP', 'name' => 'Non-equippable'],
                'purchase_price' => 0,
                'sell_price' => 0,
            ], status: 200),
        ]);

        $this->makeConnector()->send(new GetItemRequest(1));

        Saloon::assertSent(fn (GetItemRequest $r) => $r->resolveEndpoint() === '/data/wow/item/1'
        );
    }

    #[Test]
    public function it_throws_item_not_found_exception_on_404(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetItemRequest::class => MockResponse::make(
                body: ['type' => 'BLZWEBAPI00000404', 'detail' => 'Not found'],
                status: 404,
            ),
        ]);

        $this->expectException(ItemNotFoundException::class);

        $this->makeConnector()->send(new GetItemRequest(999999));
    }
}
