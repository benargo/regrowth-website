<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\Character;

use App\Http\Integrations\Blizzard\Data\Characters\CharacterMediaData;
use App\Http\Integrations\Blizzard\Exceptions\CharacterNotFoundException;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterMediaRequest;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

class GetCharacterMediaRequestTest extends BlizzardTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(): array
    {
        return [
            '_links' => [
                'self' => [
                    'href' => 'https://eu.api.blizzard.com/profile/wow/character/thunderstrike/wastedhippy/character-media?namespace=profile-classicann-eu',
                ],
            ],
            'character' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/profile/wow/character/thunderstrike/wastedhippy?namespace=profile-classicann-eu'],
                'name' => 'Wastedhippy',
                'id' => 51042439,
            ],
            'assets' => [
                ['key' => 'avatar', 'value' => 'https://render.worldofwarcraft.com/classicann-eu/character/thunderstrike/135/51042439-avatar.jpg'],
            ],
        ];
    }

    #[Test]
    public function it_casts_response_to_character_media_data(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetCharacterMediaRequest::class => MockResponse::make(body: $this->sampleApiResponse(), status: 200),
        ]);

        $response = $this->makeConnector()->send(new GetCharacterMediaRequest('thunderstrike', 'wastedhippy'));

        /** @var CharacterMediaData $dto */
        $dto = $response->dto();
        $this->assertInstanceOf(CharacterMediaData::class, $dto);
        $this->assertSame('Wastedhippy', $dto->character->name);
        $this->assertSame(51042439, $dto->character->id);
        $this->assertCount(1, $dto->assets);
        $this->assertSame('avatar', $dto->assets[0]->key);
    }

    #[Test]
    public function it_builds_a_slugged_endpoint_from_raw_realm_and_character_names(): void
    {
        $request = new GetCharacterMediaRequest('Wild Growth', 'Ben Argo');

        $this->assertSame('/profile/wow/character/wild-growth/ben-argo/character-media', $request->resolveEndpoint());
    }

    #[Test]
    public function it_accepts_already_slugged_inputs_unchanged(): void
    {
        $request = new GetCharacterMediaRequest('thunderstrike', 'wastedhippy');

        $this->assertSame('/profile/wow/character/thunderstrike/wastedhippy/character-media', $request->resolveEndpoint());
    }

    #[Test]
    public function it_sets_profile_namespace_header(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetCharacterMediaRequest::class => MockResponse::make(body: $this->sampleApiResponse(), status: 200),
        ]);

        $connector = $this->makeConnector();
        $expected = $connector->namespace('profile');

        $connector->send(new GetCharacterMediaRequest('thunderstrike', 'wastedhippy'));

        Saloon::assertSent(function ($request, $response) use ($expected) {
            return $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === $expected;
        });
    }

    #[Test]
    public function it_throws_character_not_found_exception_on_404(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetCharacterMediaRequest::class => MockResponse::make(body: ['type' => 'BLZWEBAPI00000404'], status: 404),
        ]);

        $this->expectException(CharacterNotFoundException::class);

        $this->makeConnector()->send(new GetCharacterMediaRequest('thunderstrike', 'unknown'));
    }
}
