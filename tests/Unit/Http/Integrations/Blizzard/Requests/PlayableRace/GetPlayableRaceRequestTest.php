<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\PlayableRace;

use App\Http\Integrations\Blizzard\Data\PlayableRace\PlayableRaceData;
use App\Http\Integrations\Blizzard\Exceptions\InvalidRaceException;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceRequest;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

class GetPlayableRaceRequestTest extends BlizzardTestCase
{
    #[Test]
    public function it_casts_response_to_playable_race_data(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetPlayableRaceRequest::class => MockResponse::make(body: [
                'id' => 2,
                'name' => 'Orc',
                'gender_name' => ['male' => 'Orc', 'female' => 'Orc'],
                'faction' => ['type' => 'HORDE', 'name' => 'Horde'],
                'is_selectable' => true,
                'is_allied_race' => false,
                'playable_classes' => [
                    ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-class/1'], 'name' => 'Warrior', 'id' => 1],
                ],
                'racial_spells' => [
                    ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/spell/20572'], 'name' => 'Blood Fury', 'id' => 20572],
                ],
            ], status: 200),
        ]);

        $dto = $this->makeConnector()
            ->send(new GetPlayableRaceRequest(2))
            ->dto();

        $this->assertInstanceOf(PlayableRaceData::class, $dto);
        $this->assertSame(2, $dto->id);
        $this->assertSame('Orc', $dto->name);
        $this->assertSame('HORDE', $dto->faction['type']);
        $this->assertTrue($dto->isSelectable);
        $this->assertFalse($dto->isAlliedRace);
        $this->assertCount(1, $dto->playableClasses);
        $this->assertSame('Warrior', $dto->playableClasses[0]->name);
        $this->assertCount(1, $dto->racialSpells);
        $this->assertSame('Blood Fury', $dto->racialSpells[0]->name);
    }

    #[Test]
    public function it_resolves_correct_endpoint(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetPlayableRaceRequest::class => MockResponse::make(body: [
                'id' => 1, 'name' => 'Human',
                'gender_name' => ['male' => 'Human', 'female' => 'Human'],
                'faction' => ['type' => 'ALLIANCE', 'name' => 'Alliance'],
                'is_selectable' => true, 'is_allied_race' => false,
                'playable_classes' => [], 'racial_spells' => [],
            ], status: 200),
        ]);

        $this->makeConnector()->send(new GetPlayableRaceRequest(1));

        Saloon::assertSent(fn (GetPlayableRaceRequest $r) => $r->resolveEndpoint() === '/data/wow/playable-race/1'
        );
    }

    #[Test]
    public function it_throws_invalid_race_exception_on_404(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetPlayableRaceRequest::class => MockResponse::make(
                body: ['type' => 'BLZWEBAPI00000404', 'detail' => 'Not found'],
                status: 404,
            ),
        ]);

        $this->expectException(InvalidRaceException::class);

        $this->makeConnector()->send(new GetPlayableRaceRequest(9999));
    }
}
