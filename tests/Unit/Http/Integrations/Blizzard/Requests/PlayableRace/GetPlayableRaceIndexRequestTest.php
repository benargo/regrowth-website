<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\PlayableRace;

use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceIndexRequest;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

class GetPlayableRaceIndexRequestTest extends BlizzardTestCase
{
    #[Test]
    public function it_returns_array_of_link_data(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetPlayableRaceIndexRequest::class => MockResponse::make(body: [
                'races' => [
                    ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-race/1'], 'name' => 'Human', 'id' => 1],
                    ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-race/2'], 'name' => 'Orc', 'id' => 2],
                ],
            ], status: 200),
        ]);

        $result = $this->makeConnector()
            ->send(new GetPlayableRaceIndexRequest)
            ->dto();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(LinkData::class, $result[0]);
        $this->assertSame('Human', $result[0]->name);
        $this->assertSame(1, $result[0]->id);
    }

    #[Test]
    public function it_resolves_correct_endpoint(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetPlayableRaceIndexRequest::class => MockResponse::make(body: ['races' => []], status: 200),
        ]);

        $this->makeConnector()->send(new GetPlayableRaceIndexRequest);

        Saloon::assertSent(fn (GetPlayableRaceIndexRequest $r) => $r->resolveEndpoint() === '/data/wow/playable-race/index'
        );
    }

    #[Test]
    public function it_sets_static_namespace_header(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetPlayableRaceIndexRequest::class => MockResponse::make(body: ['races' => []], status: 200),
        ]);

        $connector = $this->makeConnector();
        $expected = $connector->namespace('static');

        $connector->send(new GetPlayableRaceIndexRequest);

        Saloon::assertSent(fn ($request, $response) => $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === $expected
        );
    }
}
