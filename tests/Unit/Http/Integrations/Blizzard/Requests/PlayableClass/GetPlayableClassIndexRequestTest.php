<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\PlayableClass;

use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassIndexRequest;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

class GetPlayableClassIndexRequestTest extends BlizzardTestCase
{
    #[Test]
    public function it_returns_array_of_link_data(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetPlayableClassIndexRequest::class => MockResponse::make(body: [
                'classes' => [
                    ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-class/1'], 'name' => 'Warrior', 'id' => 1],
                    ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-class/2'], 'name' => 'Paladin', 'id' => 2],
                ],
            ], status: 200),
        ]);

        $result = $this->makeConnector()
            ->send(new GetPlayableClassIndexRequest)
            ->dto();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(LinkData::class, $result[0]);
        $this->assertSame('Warrior', $result[0]->name);
        $this->assertSame(1, $result[0]->id);
    }

    #[Test]
    public function it_resolves_correct_endpoint(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetPlayableClassIndexRequest::class => MockResponse::make(body: ['classes' => []], status: 200),
        ]);

        $this->makeConnector()->send(new GetPlayableClassIndexRequest);

        Saloon::assertSent(fn (GetPlayableClassIndexRequest $r) => $r->resolveEndpoint() === '/data/wow/playable-class/index'
        );
    }

    #[Test]
    public function it_sets_static_namespace_header(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetPlayableClassIndexRequest::class => MockResponse::make(body: ['classes' => []], status: 200),
        ]);

        $connector = $this->makeConnector();
        $expected = $connector->namespace('static');

        $connector->send(new GetPlayableClassIndexRequest);

        Saloon::assertSent(fn ($request, $response) => $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === $expected
        );
    }
}
