<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\Character;

use App\Http\Integrations\Blizzard\Data\Characters\CharacterStatusData;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterStatusRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

#[Group('blizzard-integration')]
class GetCharacterStatusRequestTest extends BlizzardTestCase
{
    #[Test]
    public function it_returns_character_status_dto(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetCharacterStatusRequest::class => MockResponse::make(
                body: ['id' => 12345, 'is_valid' => true],
                status: 200,
            ),
        ]);

        $dto = $this->makeConnector()
            ->send(new GetCharacterStatusRequest('thunderstrike', 'Foo'))
            ->dto();

        $this->assertInstanceOf(CharacterStatusData::class, $dto);
        $this->assertSame(12345, $dto->id);
        $this->assertTrue($dto->isValid);

        Saloon::assertSent(fn (GetCharacterStatusRequest $r) => $r->resolveEndpoint() === '/profile/wow/character/thunderstrike/foo/status'
        );
    }

    #[Test]
    public function it_builds_a_slugged_endpoint_from_raw_realm_and_character_names(): void
    {
        $request = new GetCharacterStatusRequest('Wild Growth', 'Ben Argo');

        $this->assertSame('/profile/wow/character/wild-growth/ben-argo/status', $request->resolveEndpoint());
    }
}
