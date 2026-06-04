<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\PlayableClass;

use App\Http\Integrations\Blizzard\Data\PlayableClass\PlayableClassData;
use App\Http\Integrations\Blizzard\Exceptions\InvalidClassException;
use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassRequest;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

class GetPlayableClassRequestTest extends BlizzardTestCase
{
    #[Test]
    public function it_casts_response_to_playable_class_data(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetPlayableClassRequest::class => MockResponse::make(body: [
                'id' => 7,
                'name' => 'Shaman',
                'gender_name' => ['male' => 'Shaman', 'female' => 'Shaman'],
                'power_type' => [
                    'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/power-type/0'],
                    'name' => 'Mana',
                    'id' => 0,
                ],
                'media' => [
                    'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/media/playable-class/7'],
                    'id' => 7,
                ],
                'pvp_talent_slots' => [
                    'href' => 'https://eu.api.blizzard.com/data/wow/playable-class/7/pvp-talent-slots',
                ],
                'playable_races' => [
                    ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-race/6'], 'name' => 'Tauren', 'id' => 6],
                ],
            ], status: 200),
        ]);

        $dto = $this->makeConnector()
            ->send(new GetPlayableClassRequest(7))
            ->dto();

        $this->assertInstanceOf(PlayableClassData::class, $dto);
        $this->assertSame(7, $dto->id);
        $this->assertSame('Shaman', $dto->name);
        $this->assertSame('Mana', $dto->powerType->name);
        $this->assertSame(7, $dto->media->id);
        $this->assertCount(1, $dto->playableRaces);
        $this->assertSame('Tauren', $dto->playableRaces[0]->name);
    }

    #[Test]
    public function it_resolves_correct_endpoint(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetPlayableClassRequest::class => MockResponse::make(body: [
                'id' => 1, 'name' => 'Warrior',
                'gender_name' => ['male' => 'Warrior', 'female' => 'Warrior'],
                'power_type' => ['key' => ['href' => 'p'], 'name' => 'Rage', 'id' => 1],
                'media' => ['key' => ['href' => 'm'], 'id' => 1],
                'pvp_talent_slots' => ['href' => 'pvp'],
                'playable_races' => [],
            ], status: 200),
        ]);

        $this->makeConnector()->send(new GetPlayableClassRequest(1));

        Saloon::assertSent(fn (GetPlayableClassRequest $r) => $r->resolveEndpoint() === '/data/wow/playable-class/1'
        );
    }

    #[Test]
    public function it_throws_invalid_class_exception_on_404(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetPlayableClassRequest::class => MockResponse::make(
                body: ['type' => 'BLZWEBAPI00000404', 'detail' => 'Not found'],
                status: 404,
            ),
        ]);

        $this->expectException(InvalidClassException::class);

        $this->makeConnector()->send(new GetPlayableClassRequest(9999));
    }
}
